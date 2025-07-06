# SharedKernel Security Module Design

## Overview

This document outlines the design for the SharedKernel Security module, which provides authentication, authorization, and command-level access control for the DDD-based application. The module follows the existing decorator pattern and supports multiple authentication providers across different bounded contexts.

## Architecture Goals

- **Unified Security Interface**: Common security contracts across all bounded contexts
- **Provider Pattern**: Different authentication mechanisms (AWS Cognito, FuelPHP) while sharing common interfaces
- **Command-Level Authorization**: Decorator pattern for securing command execution
- **Role-Based Access Control**: Flexible permission system based on user roles
- **Separation of Concerns**: Authentication vs Authorization vs Command Security

## Core Components

### 1. Security Context

**Purpose**: Manages the current authenticated user throughout the request lifecycle.

**Interface**:
```php
interface SecurityContextInterface
{
    public function getCurrentUser(): ?AuthenticatedUser;
    public function setCurrentUser(AuthenticatedUser $user): void;
    public function hasAuthenticatedUser(): bool;
    public function clearUser(): void;
}
```

**Responsibilities**:
- Store current user session/request context
- Provide thread-safe access to authenticated user
- Integration point for different authentication providers

### 2. Authentication Service Interface

**Purpose**: Provides framework-agnostic authentication contract for different bounded contexts.

**Interface**:
```php
interface AuthenticationServiceInterface
{
    public function authenticate(string $username, string $password): AuthenticatedUser;
    public function authenticateByToken(string $token): AuthenticatedUser;  // For token-based contexts (Cognito)
    public function authenticateBySession(): AuthenticatedUser;             // For session-based contexts (FuelPHP)
    public function refresh(): bool;
    public function logout(): void;
}
```

**Note**: Concrete implementations will be provided by individual bounded contexts (Admin, Marketing) to support their specific authentication mechanisms. Context-specific methods may throw `NotSupportedException` for unsupported authentication types.

### 3. Permission Management System

**Purpose**: Maps user roles to command permissions and validates access.

**Interface**:
```php
interface PermissionServiceInterface
{
    public function hasPermission(AuthenticatedUser $user, string $permission): bool;
    public function getPermissionsForRole(string $role): array;
    public function getAllPermissionsForUser(AuthenticatedUser $user): array;
    public function hasCommandAccess(AuthenticatedUser $user, string $commandName): bool;
}
```

**Implementation Strategy**:
- Configuration-based permission mapping
- Multiple role support with union-based permission resolution
- Command-specific permission checking
- Cached permission resolution for performance

**Multi-Role Permission Resolution**:
```php
public function hasPermission(AuthenticatedUser $user, string $permission): bool
{
    // Check each role - any role with permission grants access
    foreach ($user->getRoles() as $role) {
        if ($this->roleHasPermission($role, $permission)) {
            return true; // First match grants access
        }
    }
    return false; // No role has the permission
}

public function getAllPermissionsForUser(AuthenticatedUser $user): array
{
    $permissions = [];
    
    // Collect permissions from all roles
    foreach ($user->getRoles() as $role) {
        $permissions = array_merge($permissions, $this->getPermissionsForRole($role));
    }
    
    // Return unique permissions (union of all role permissions)
    return array_unique($permissions);
}
```

### 4. Command Security Integration

**Purpose**: Secure command execution through decorator pattern.

#### SecurableCommandInterface
```php
interface SecurableCommandInterface
{
    public function getRequiredPermission(): string;
    public function getRequiredRoles(): array;
    public function isPublicCommand(): bool;
}
```

**Implementation Guidelines**:
- Commands requiring authorization implement this interface
- Return specific permission string (e.g., "user.create", "order.delete")
- Support for role-based access (e.g., ["admin", "manager"])
- Public commands (no authorization required) return `isPublicCommand() = true`

#### SecurityCommandDecorator
```php
class SecurityCommandDecorator implements CommandHandlerInterface
{
    private CommandHandlerInterface $next;
    private SecurityContextInterface $securityContext;
    private PermissionServiceInterface $permissionService;
    
    public function handle(CommandInterface $command): void
    {
        // Authorization logic before command execution
        $this->next->handle($command);
    }
}
```

**Authorization Flow**:
1. Check if command implements `SecurableCommandInterface`
2. If public command, execute directly
3. Get current authenticated user from security context
4. Validate user has required permissions/roles
5. Execute command if authorized, throw `UnauthorizedException` if not

### 5. Exception Handling

**Security Exceptions**:
```php
class UnauthorizedException extends DomainException
class UnauthenticatedException extends DomainException
class InsufficientPermissionsException extends UnauthorizedException
```

**Usage**:
- `UnauthenticatedException`: No authenticated user in context
- `UnauthorizedException`: User lacks required permissions
- `InsufficientPermissionsException`: Specific permission missing

## Permission System Design

### Permission Naming Convention
```
{resource}.{action}
```

**Examples**:
- `user.create` - Create new users
- `user.update` - Update user information
- `user.delete` - Delete users
- `order.view` - View orders
- `order.process` - Process orders
- `admin.access` - Access admin panel

### Multiple Roles Support

**Design Principle**: Users can have multiple roles simultaneously, with permissions being the **union** of all their roles.

**Permission Resolution Strategy**:
- **Additive Permissions**: User gets all permissions from all assigned roles
- **Any Role Grants Access**: If any role has the required permission, access is granted
- **No Role Conflicts**: Multiple roles cannot revoke permissions from each other

**Examples**:
```php
// User with multiple roles and profile metadata
$user = new AuthenticatedUser(
    '123', 
    'john@example.com', 
    ['user', 'manager', 'report.viewer'],
    [
        'department' => 'Sales',
        'employee_id' => 'EMP001',
        'company' => 'Acme Corp'
    ]
);

// Permission check - any role with permission grants access
hasPermission($user, 'user.view'); // true (from 'user' role)
hasPermission($user, 'user.create'); // true (from 'manager' role)  
hasPermission($user, 'reports.generate'); // true (from 'report.viewer' role)
```

**Common Multi-Role Scenarios**:
- **Cross-Context Access**: `['admin.marketing', 'user.sales']` - Admin in Marketing, User in Sales
- **Progressive Permissions**: `['user', 'manager']` - Keeps user permissions when promoted
- **Specialized Roles**: `['user', 'report.viewer', 'order.processor']` - Base + specific capabilities
- **Temporal Roles**: `['user', 'temp.admin']` - Temporary elevated access

### Role Hierarchy (Individual Role Ranking)
```
admin > manager > user
```

**Note**: Hierarchy applies to individual roles for organizational purposes. Users with multiple roles get combined permissions regardless of hierarchy.

**Role Definitions**:
- `admin`: Full system access
- `manager`: Business operations management
- `user`: Standard user operations

### Permission Configuration
```php
// Example configuration structure
return [
    'roles' => [
        'admin' => ['*'], // All permissions
        'manager' => ['user.view', 'user.create', 'order.*'],
        'user' => ['user.view.own', 'order.view.own'],
        // Specialized roles for multi-role scenarios
        'report.viewer' => ['reports.view', 'reports.generate'],
        'order.processor' => ['order.process', 'order.update'],
        'temp.admin' => ['user.create', 'user.update'], // Temporary elevated access
        'admin.marketing' => ['marketing.*'], // Context-specific admin
        'user.sales' => ['sales.view', 'sales.create']
    ],
    'commands' => [
        'CreateUserCommand' => 'user.create',
        'UpdateUserCommand' => 'user.update',
        'DeleteUserCommand' => 'user.delete',
        'ViewOrderCommand' => 'order.view',
        'ProcessOrderCommand' => 'order.process',
        'GenerateReportCommand' => 'reports.generate'
    ]
];
```

**Multi-Role User Examples**:
```php
// Progressive permissions - user promoted to manager keeps user permissions
$manager = new AuthenticatedUser(
    '1', 
    'manager@example.com', 
    ['user', 'manager'],
    ['department' => 'Operations', 'employee_id' => 'MGR001']
);
// Has: user.view.own, order.view.own, user.view, user.create, order.*

// Specialized access - user with specific capabilities
$specialist = new AuthenticatedUser(
    '2', 
    'specialist@example.com', 
    ['user', 'report.viewer', 'order.processor'],
    ['specialization' => 'Analytics', 'clearance_level' => 'L2']
);
// Has: user.view.own, order.view.own, reports.view, reports.generate, order.process, order.update

// Cross-context access - admin in marketing, user in sales
$crossContext = new AuthenticatedUser(
    '3', 
    'cross@example.com', 
    ['admin.marketing', 'user.sales'],
    ['marketing_region' => 'North', 'sales_territory' => 'Enterprise']
);
// Has: marketing.*, sales.view, sales.create
```

## Integration with Existing Architecture

### Decorator Chain Order
```
SecurityCommandDecorator -> LoggerCommandDecorator -> TransactionalCommandDecorator -> ActualCommandHandler
```

**Rationale**:
1. Security check first (fail fast)
2. Log authorized commands
3. Execute in transaction


## Bounded Context Integration

### Core SharedKernel Components
- **Security Context**: Manages authenticated user across all contexts
- **Permission Service**: Unified permission checking logic
- **Security Decorator**: Consistent command authorization
- **Authentication Interface**: Common contract for all auth providers

### Context-Specific Responsibilities
- **Authentication Implementation**: Each bounded context implements `AuthenticationServiceInterface` with appropriate methods
- **User Storage**: Context-specific user profile management
- **Permission Configuration**: Context-specific role and permission mappings
- **Session/Token Management**: Context-appropriate authentication state handling (sessions for FuelPHP, tokens for Cognito)

### Integration Points
- Common `AuthenticatedUser` value object with profile metadata
- Unified permission checking through SharedKernel
- Consistent security decorator behavior across contexts
- Context-specific profile wrapper classes for type-safe profile access

## AuthenticatedUser Design

### Core AuthenticatedUser Class

**Purpose**: Represents an authenticated user with core identity information and flexible profile metadata.

**Class Definition**:
```php
class AuthenticatedUser
{
    public function __construct(
        private string $id,
        private string $email,
        private array $roles,
        private array $profileMetadata = []
    ) {}
    
    // Core identity methods
    public function getId(): string { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getRoles(): array { return $this->roles; }
    
    // Profile metadata methods
    public function getProfileMetadata(): array { return $this->profileMetadata; }
    public function getProfileValue(string $key): mixed { return $this->profileMetadata[$key] ?? null; }
    public function hasProfileValue(string $key): bool { return isset($this->profileMetadata[$key]); }
    
    // Convenience methods
    public function hasRole(string $role): bool { return in_array($role, $this->roles); }
    public function equals(AuthenticatedUser $other): bool { return $this->id === $other->getId(); }
}
```

### Context-Specific Profile Wrappers

**Purpose**: Provide type-safe, context-specific access to profile metadata.

#### Admin Context Profile Wrapper
```php
class AdminUserProfile
{
    public function __construct(private AuthenticatedUser $user) {}
    
    public function getUser(): AuthenticatedUser { return $this->user; }
    
    public function getDepartment(): ?string 
    { 
        return $this->user->getProfileValue('department'); 
    }
    
    public function getEmployeeId(): ?string 
    { 
        return $this->user->getProfileValue('employee_id'); 
    }
    
    public function getAccessLevel(): string 
    { 
        return $this->user->getProfileValue('access_level') ?? 'basic'; 
    }
    
    public function getLastLogin(): ?DateTime 
    { 
        $timestamp = $this->user->getProfileValue('last_login');
        return $timestamp ? new DateTime($timestamp) : null;
    }
}
```

#### Marketing Context Profile Wrapper
```php
class MarketingUserProfile
{
    public function __construct(private AuthenticatedUser $user) {}
    
    public function getUser(): AuthenticatedUser { return $this->user; }
    
    public function getCompany(): ?string 
    { 
        return $this->user->getProfileValue('company'); 
    }
    
    public function getLeadSource(): ?string 
    { 
        return $this->user->getProfileValue('lead_source'); 
    }
    
    public function getSubscriptionStatus(): string 
    { 
        return $this->user->getProfileValue('subscription_status') ?? 'inactive'; 
    }
    
    public function getCampaignPreferences(): array 
    { 
        return $this->user->getProfileValue('campaign_preferences') ?? []; 
    }
}
```

### Authentication Service Integration

**Example**: How authentication services populate profile metadata

#### Admin Context Authentication
```php
class CognitoAuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private CognitoClient $cognitoClient,
        private UserProfileRepositoryInterface $profileRepository
    ) {}
    
    public function authenticate(string $username, string $password): AuthenticatedUser
    {
        // 1. Authenticate with Cognito (clean - only auth concern)
        $cognitoUser = $this->cognitoClient->authenticate($username, $password);
        
        // 2. Get profile metadata and roles from database
        $profile = $this->profileRepository->findByCognitoId($cognitoUser->getId());
        
        // 3. Combine into AuthenticatedUser
        return new AuthenticatedUser(
            $cognitoUser->getId(),           // Use Cognito ID as primary
            $cognitoUser->getEmail(),        // Email from Cognito
            $profile ? $profile->getRoles() : [], // Roles from database
            $profile ? $profile->getMetadata() : [] // Profile from database
        );
    }
    
    public function authenticateByToken(string $token): AuthenticatedUser
    {
        // 1. Validate JWT with Cognito
        $claims = $this->cognitoClient->validateJwtToken($token);
        
        // 2. Get profile and roles from database
        $profile = $this->profileRepository->findByCognitoId($claims['sub']);
        
        // 3. Combine
        return new AuthenticatedUser(
            $claims['sub'],                  // Cognito ID
            $claims['email'],                // Email from token
            $profile ? $profile->getRoles() : [], // Roles from database
            $profile ? $profile->getMetadata() : [] // Profile from database
        );
    }
}
```

#### Marketing Context Authentication
```php
class FuelPhpAuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private UserProfileRepositoryInterface $profileRepository
    ) {}
    
    public function authenticate(string $username, string $password): AuthenticatedUser
    {
        // 1. Authenticate with FuelPHP (clean - only auth concern)
        if (Auth::login($username, $password)) {
            $fuelUser = Auth::user();
            
            // 2. Get profile metadata and roles from database
            $profile = $this->profileRepository->findByFuelPhpId($fuelUser->id);
            
            // 3. Combine into AuthenticatedUser
            return new AuthenticatedUser(
                (string) $fuelUser->id,          // Use FuelPHP ID as primary
                $fuelUser->email,                // Email from FuelPHP
                $profile ? $profile->getRoles() : [], // Roles from database
                $profile ? $profile->getMetadata() : [] // Profile from database
            );
        }
        
        throw new AuthenticationException('Invalid credentials');
    }
    
    public function authenticateByToken(string $token): AuthenticatedUser
    {
        // FuelPHP doesn't support token-based authentication
        throw new NotSupportedException('Token authentication not supported in FuelPHP context');
    }
    
    public function authenticateBySession(): AuthenticatedUser
    {
        // 1. Check FuelPHP session authentication
        if (Auth::check()) {
            $fuelUser = Auth::user();
            
            // 2. Get profile and roles from database
            $profile = $this->profileRepository->findByFuelPhpId($fuelUser->id);
            
            // 3. Combine
            return new AuthenticatedUser(
                (string) $fuelUser->id,          // FuelPHP ID
                $fuelUser->email,                // Email from FuelPHP
                $profile ? $profile->getRoles() : [], // Roles from database
                $profile ? $profile->getMetadata() : [] // Profile from database
            );
        }
        
        throw new UnauthenticatedException('No active FuelPHP session');
    }
}
```

### Usage Examples

#### In Admin Context Services
```php
class AdminUserService
{
    private SecurityContextInterface $securityContext;
    
    public function getCurrentUserDepartment(): ?string
    {
        $user = $this->securityContext->getCurrentUser();
        if (!$user) {
            return null;
        }
        
        $adminProfile = new AdminUserProfile($user);
        return $adminProfile->getDepartment();
    }
}
```

#### In Marketing Context Services
```php
class MarketingCampaignService
{
    private SecurityContextInterface $securityContext;
    
    public function getUserCampaignPreferences(): array
    {
        $user = $this->securityContext->getCurrentUser();
        if (!$user) {
            return [];
        }
        
        $marketingProfile = new MarketingUserProfile($user);
        return $marketingProfile->getCampaignPreferences();
    }
}
```

### Database Design

#### User Profile Tables
```sql
-- Admin context user profiles (Cognito-based)
CREATE TABLE admin_user_profiles (
    cognito_id VARCHAR(36) PRIMARY KEY,  -- Cognito user ID
    roles JSON,                          -- Roles stored in database
    metadata JSON,                       -- Profile metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Marketing context user profiles (FuelPHP-based)
CREATE TABLE marketing_user_profiles (
    fuelphp_id INTEGER PRIMARY KEY,      -- FuelPHP user ID
    roles JSON,                          -- Roles stored in database
    metadata JSON,                       -- Profile metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Benefits of Database-First Approach

1. **SharedKernel Purity**: Core user class remains context-agnostic
2. **Type Safety**: Wrapper classes provide typed access to profile data
3. **Flexibility**: Easy to add new profile fields without changing core classes
4. **Clean Separation**: Authentication providers handle auth only, database handles business data
5. **Testability**: Simple to mock profile repository separately from auth providers
6. **Maintainability**: Profile and role changes don't affect authentication logic
7. **Single Source of Truth**: All business data (roles + profile) in database

## Implementation Scope

### Core Security Infrastructure
1. **Security Interfaces**: Define contracts for security context, permissions, and authentication
2. **AuthenticatedUser Class**: Implement user identity with profile metadata support
3. **Security Command Decorator**: Implement authorization logic for command execution
4. **Permission Service**: Create configuration-based permission management
5. **Security Context**: Manage authenticated user throughout request lifecycle
6. **Security Exceptions**: Define domain-specific security exceptions
7. **DI Container Integration**: Wire up security components in dependency injection

### Out of Scope
- **Authentication Provider Implementations**: Specific authentication mechanisms (AWS Cognito, FuelPHP) will be implemented by individual bounded contexts
- **Context-Specific Profile Wrappers**: Profile wrapper classes will be implemented by individual bounded contexts
- **User Profile Repository Implementations**: Context-specific user profile storage and management
- **Token Validation**: Provider-specific token handling and validation
- **Session Management**: Context-appropriate session handling mechanisms
- **Profile Management Commands**: Context-specific profile creation and update commands

### Testing Strategy
1. **Unit Testing**: Test security components in isolation with mocked dependencies
2. **Integration Testing**: Test security decorator with command handlers
3. **Permission Testing**: Validate permission resolution logic and multi-role scenarios
4. **Profile Metadata Testing**: Test AuthenticatedUser profile metadata functionality
5. **Security Testing**: Test authorization bypass prevention and access control

## Security Considerations

### Best Practices
- **Principle of Least Privilege**: Users get minimum required permissions
- **Fail Secure**: Deny access by default, explicit grants required
- **Audit Trail**: Log all authorization decisions
- **Token Security**: Secure storage and transmission of authentication tokens

### Threat Mitigation
- **Authorization Bypass**: Decorator pattern ensures all commands go through security check
- **Privilege Escalation**: Role hierarchy prevents unauthorized privilege increases
- **Session Management**: Proper token validation and expiration handling
- **Injection Attacks**: Parameterized permission checks prevent injection

## Testing Strategy

### Unit Testing
- Test each security component in isolation
- Mock dependencies for focused testing
- Test permission resolution logic
- Validate exception handling

### Integration Testing
- Test security decorator with real command handlers
- Test permission service with actual configurations
- End-to-end security flow testing with mock authentication

### Security Testing
- Test authorization bypass attempts
- Validate privilege escalation prevention
- Test with invalid/expired tokens
- Performance testing with permission caching