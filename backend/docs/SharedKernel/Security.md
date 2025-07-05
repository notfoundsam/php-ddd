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

### 2. Enhanced Authentication Service

**Current State**: Basic username/password authentication
**Enhancement**: Support for multiple authentication providers

**Extended Interface**:
```php
interface AuthenticationServiceInterface
{
    public function authenticate(string $username, string $password): AuthenticatedUser;
    public function authenticateByToken(string $token): AuthenticatedUser;
    public function refresh(): bool;
    public function logout(): void;
}
```

**Provider Implementations**:
- `CognitoAuthenticationService` - AWS Cognito integration for Admin context
- `FuelPhpAuthenticationService` - FuelPHP session-based auth for Marketing context

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
// User with multiple roles
$user = new AuthenticatedUser('123', 'john@example.com', ['user', 'manager', 'report.viewer']);

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
admin > manager > user > guest
```

**Note**: Hierarchy applies to individual roles for organizational purposes. Users with multiple roles get combined permissions regardless of hierarchy.

**Role Definitions**:
- `admin`: Full system access
- `manager`: Business operations management
- `user`: Standard user operations
- `guest`: Public access only

### Permission Configuration
```php
// Example configuration structure
return [
    'roles' => [
        'admin' => ['*'], // All permissions
        'manager' => ['user.view', 'user.create', 'order.*'],
        'user' => ['user.view.own', 'order.view.own'],
        'guest' => [],
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
$manager = new AuthenticatedUser('1', 'manager@example.com', ['user', 'manager']);
// Has: user.view.own, order.view.own, user.view, user.create, order.*

// Specialized access - user with specific capabilities
$specialist = new AuthenticatedUser('2', 'specialist@example.com', ['user', 'report.viewer', 'order.processor']);
// Has: user.view.own, order.view.own, reports.view, reports.generate, order.process, order.update

// Cross-context access - admin in marketing, user in sales
$crossContext = new AuthenticatedUser('3', 'cross@example.com', ['admin.marketing', 'user.sales']);
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

### DI Container Configuration
```php
// Example DI configuration
$container->set(SecurityContextInterface::class, SecurityContext::class);
$container->set(PermissionServiceInterface::class, ConfigPermissionService::class);

// Context-specific authentication providers
$container->set('admin.auth', CognitoAuthenticationService::class);
$container->set('marketing.auth', FuelPhpAuthenticationService::class);
```

## Bounded Context Integration

### Admin Context (AWS Cognito)
- **Authentication**: AWS Cognito JWT tokens
- **User Storage**: `admin_users` table for profile data
- **Permissions**: Role-based from Cognito groups
- **Session Management**: JWT token validation

### Marketing Context (FuelPHP)
- **Authentication**: FuelPHP session-based
- **User Storage**: Existing FuelPHP user tables
- **Permissions**: Role-based from database
- **Session Management**: FuelPHP session handling

### Shared Security Context
- Common `AuthenticatedUser` value object
- Unified permission checking
- Consistent security decorator behavior

## Implementation Phases

### Phase 1: Core Infrastructure
1. Create security interfaces and base implementations
2. Implement `SecurityCommandDecorator`
3. Create permission service with configuration support
4. Add security context management

### Phase 2: Authentication Providers
1. Implement AWS Cognito provider for Admin context
2. Implement FuelPHP provider for Marketing context
3. Create user profile management
4. Add token validation and refresh

### Phase 3: Integration & Testing
1. Wire up security decorator in DI container
2. Configure permission mappings
3. Add security to existing commands
4. Create comprehensive test coverage

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
- Validate authentication provider integration
- Test permission service with actual configurations
- End-to-end security flow testing

### Security Testing
- Test authorization bypass attempts
- Validate privilege escalation prevention
- Test with invalid/expired tokens
- Performance testing with permission caching