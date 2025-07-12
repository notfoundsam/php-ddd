# SharedKernel Security Module Design

## Overview

This document outlines the design for the SharedKernel Security module, which provides authentication, authorization, and command-level access control for the DDD-based application. The module follows the existing decorator pattern and supports multiple authentication providers across different bounded contexts.

## Architecture Goals

- **Unified Security Interface**: Common security contracts across all bounded contexts
- **Provider Pattern**: Different authentication mechanisms (AWS Cognito for Admin, FuelPHP for CustomerPortal) while sharing common interfaces
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

**Note**: Concrete implementations will be provided by individual bounded contexts (Admin, CustomerPortal) to support their specific authentication mechanisms. Context-specific methods may throw `NotSupportedException` for unsupported authentication types.

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

**Purpose**: Secure command execution through decorator pattern using configuration-based authorization.

#### Configuration-Based Command Security

**Security Configuration Structure**:
```php
// config/security.php
return [
    'command_permissions' => [
        'CreateUserCommand' => [
            'permission' => 'user.create',
            'roles' => ['admin', 'manager']
        ],
        'UpdateUserCommand' => [
            'permission' => 'user.update', 
            'roles' => ['admin', 'manager']
        ],
        'DeleteUserCommand' => [
            'permission' => 'user.delete',
            'roles' => ['admin']
        ],
        'ViewOrderCommand' => [
            'permission' => 'order.view',
            'roles' => ['admin', 'manager', 'user']
        ]
    ],
    'public_commands' => [
        'HealthCheckCommand',
        'PublicApiCommand'
    ]
];
```

#### SecurityCommandDecorator
```php
class SecurityCommandDecorator implements CommandHandlerInterface
{
    public function __construct(
        private CommandHandlerInterface $next,
        private SecurityContextInterface $securityContext,
        private PermissionServiceInterface $permissionService,
        private array $commandPermissions,
        private array $publicCommands
    ) {}
    
    public function handle(CommandInterface $command): void
    {
        $commandName = get_class($command);
        
        // 1. Check if command is public (no authorization required)
        if (in_array($commandName, $this->publicCommands)) {
            $this->next->handle($command);
            return;
        }
        
        // 2. Get current authenticated user
        $user = $this->securityContext->getCurrentUser();
        if (!$user) {
            throw new UnauthenticatedException('Authentication required');
        }
        
        // 3. Check command authorization configuration
        if (!isset($this->commandPermissions[$commandName])) {
            throw new SecurityConfigurationException("No security configuration found for command: {$commandName}");
        }
        
        $config = $this->commandPermissions[$commandName];
        
        // 4. Validate permission if specified
        if (isset($config['permission'])) {
            if (!$this->permissionService->hasPermission($user, $config['permission'])) {
                throw new InsufficientPermissionsException($config['permission']);
            }
        }
        
        // 5. Validate role if specified
        if (isset($config['roles'])) {
            $hasRequiredRole = false;
            foreach ($config['roles'] as $role) {
                if ($user->hasRole($role)) {
                    $hasRequiredRole = true;
                    break;
                }
            }
            if (!$hasRequiredRole) {
                throw new UnauthorizedException('Insufficient role privileges');
            }
        }
        
        // 6. Execute command if authorized
        $this->next->handle($command);
    }
}
```

**Authorization Flow**:
1. Get command class name for configuration lookup
2. Check if command is in public commands whitelist
3. Get current authenticated user from security context
4. Look up command security configuration
5. Validate required permission if configured
6. Validate required roles if configured  
7. Execute command if authorized, throw exception if not

**Benefits of Configuration-Based Approach**:
- **Security by Default**: Commands without configuration are denied
- **Centralized Control**: All command security rules in one place
- **No Code Changes**: Security changes don't require command modifications
- **Audit Friendly**: Easy to review all security configurations
- **Testable**: Configuration can be easily mocked for testing

### 5. Exception Handling

**Security Exceptions**:
```php
// Base authentication exception
class AuthenticationException extends DomainException
{
    public function __construct(string $message = 'Authentication failed', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// Base authorization exception
class UnauthorizedException extends DomainException
{
    public function __construct(string $message = 'Access denied', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// No authenticated user in security context
class UnauthenticatedException extends AuthenticationException
{
    public function __construct(string $message = 'No authenticated user', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// Specific permission missing
class InsufficientPermissionsException extends UnauthorizedException
{
    private string $requiredPermission;
    
    public function __construct(string $permission, string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        $this->requiredPermission = $permission;
        $message = $message ?? "Access denied. Required permission: {$permission}";
        parent::__construct($message, $code, $previous);
    }
    
    public function getRequiredPermission(): string
    {
        return $this->requiredPermission;
    }
}

// Authentication provider not supported
class NotSupportedException extends AuthenticationException
{
    public function __construct(string $message = 'Authentication method not supported', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// Token validation failure
class InvalidTokenException extends AuthenticationException
{
    public function __construct(string $message = 'Invalid or expired token', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

// Security configuration error
class SecurityConfigurationException extends DomainException
{
    public function __construct(string $message = 'Security configuration error', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

**Exception Hierarchy**:
```
DomainException
├── AuthenticationException
│   ├── UnauthenticatedException
│   ├── NotSupportedException
│   └── InvalidTokenException
├── UnauthorizedException
│   └── InsufficientPermissionsException
└── SecurityConfigurationException
```

**Usage Guidelines**:
- `AuthenticationException`: Generic authentication failures (invalid credentials, user not found)
- `UnauthenticatedException`: No authenticated user in security context
- `UnauthorizedException`: User lacks required permissions or roles
- `InsufficientPermissionsException`: Specific permission missing (includes permission name)
- `InvalidTokenException`: Token validation failures (expired, malformed, invalid signature)
- `NotSupportedException`: Authentication method not available in current context
- `SecurityConfigurationException`: Missing or invalid security configuration

**Security Considerations**:
- Use generic error messages to prevent user enumeration attacks
- Log detailed error information server-side while returning generic messages to clients
- Include audit trail information for security exceptions

### Standardized Error Messages

**Purpose**: Prevent information leakage and user enumeration attacks by using consistent, generic error messages while maintaining detailed server-side logging.

#### Security Error Message Standards

**Authentication Errors**:
```php
class SecurityErrorMessages
{
    // Generic authentication failure - prevents user enumeration
    public const AUTHENTICATION_FAILED = 'Invalid credentials';
    
    // Generic authorization failure
    public const ACCESS_DENIED = 'Access denied';
    
    // Generic token errors
    public const INVALID_TOKEN = 'Invalid or expired token';
    
    // Session errors
    public const SESSION_EXPIRED = 'Your session has expired. Please log in again';
    public const SESSION_INVALID = 'Invalid session';
    
    // Configuration errors (internal only)
    public const SECURITY_CONFIG_ERROR = 'Security configuration error';
    
    // Rate limiting (when implemented)
    public const TOO_MANY_ATTEMPTS = 'Too many attempts. Please try again later';
}
```

#### Error Response Handler

```php
class SecurityErrorHandler
{
    public function __construct(
        private SecurityAuditLoggerInterface $auditLogger
    ) {}
    
    public function handleAuthenticationError(
        Exception $originalException,
        string $username = null,
        array $context = []
    ): AuthenticationException {
        // Log detailed error server-side
        $this->auditLogger->logSecurityException(
            $username ?? 'unknown',
            get_class($originalException),
            $originalException->getMessage(),
            array_merge($context, [
                'file' => $originalException->getFile(),
                'line' => $originalException->getLine(),
                'original_message' => $originalException->getMessage()
            ])
        );
        
        // Return generic error to client
        return new AuthenticationException(SecurityErrorMessages::AUTHENTICATION_FAILED);
    }
    
    public function handleAuthorizationError(
        Exception $originalException,
        string $userId = null,
        string $resource = null,
        array $context = []
    ): UnauthorizedException {
        // Log detailed error server-side
        $this->auditLogger->logSecurityException(
            $userId ?? 'unknown',
            get_class($originalException),
            $originalException->getMessage(),
            array_merge($context, [
                'resource' => $resource,
                'original_message' => $originalException->getMessage()
            ])
        );
        
        // Return generic error to client
        if ($originalException instanceof InsufficientPermissionsException) {
            return new UnauthorizedException(SecurityErrorMessages::ACCESS_DENIED);
        }
        
        return new UnauthorizedException(SecurityErrorMessages::ACCESS_DENIED);
    }
    
    public function handleTokenError(
        Exception $originalException,
        string $tokenType = 'access',
        array $context = []
    ): InvalidTokenException {
        // Log detailed error server-side
        $this->auditLogger->logSecurityException(
            'unknown',
            get_class($originalException),
            $originalException->getMessage(),
            array_merge($context, [
                'token_type' => $tokenType,
                'original_message' => $originalException->getMessage()
            ])
        );
        
        // Return generic error to client
        return new InvalidTokenException(SecurityErrorMessages::INVALID_TOKEN);
    }
}
```

#### Updated Authentication Service with Standardized Errors

```php
class FileBasedMockAuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private UserProfileRepositoryInterface $profileRepository,
        private SecurityAuditLoggerInterface $auditLogger,
        private SecurityErrorHandler $errorHandler,
        private string $usersFilePath = null
    ) {}
    
    public function authenticate(string $username, string $password): AuthenticatedUser
    {
        try {
            $users = $this->loadUsersFromFile();
            
            // Check if user exists
            if (!isset($users[$username])) {
                // Log specific error server-side
                $this->auditLogger->logAuthenticationAttempt(
                    $username,
                    false,
                    'file_based',
                    ['error' => 'user_not_found']
                );
                
                // Return generic error to client
                throw new AuthenticationException(SecurityErrorMessages::AUTHENTICATION_FAILED);
            }
            
            // Verify password
            if (!password_verify($password, $users[$username]['password'])) {
                // Log specific error server-side
                $this->auditLogger->logAuthenticationAttempt(
                    $username,
                    false,
                    'file_based',
                    ['error' => 'invalid_password']
                );
                
                // Return generic error to client
                throw new AuthenticationException(SecurityErrorMessages::AUTHENTICATION_FAILED);
            }
            
            // Success path remains unchanged...
            
        } catch (AuthenticationException $e) {
            // Re-throw authentication exceptions as-is (already standardized)
            throw $e;
        } catch (Exception $e) {
            // Handle unexpected errors with standardized response
            throw $this->errorHandler->handleAuthenticationError($e, $username, [
                'method' => 'file_based_authenticate'
            ]);
        }
    }
    
    public function authenticateByToken(string $token): AuthenticatedUser
    {
        try {
            // Token validation logic...
            
        } catch (InvalidTokenException $e) {
            // Re-throw token exceptions as-is (already standardized)
            throw $e;
        } catch (Exception $e) {
            // Handle unexpected errors with standardized response
            throw $this->errorHandler->handleTokenError($e, 'access', [
                'method' => 'file_based_authenticate_by_token'
            ]);
        }
    }
}
```

#### Updated SecurityCommandDecorator with Standardized Errors

```php
class SecurityCommandDecorator implements CommandHandlerInterface
{
    public function __construct(
        private CommandHandlerInterface $next,
        private SecurityContextInterface $securityContext,
        private PermissionServiceInterface $permissionService,
        private SecurityAuditLoggerInterface $auditLogger,
        private SecurityErrorHandler $errorHandler,
        private array $commandPermissions,
        private array $publicCommands
    ) {}
    
    public function handle(CommandInterface $command): void
    {
        $commandName = get_class($command);
        $user = $this->securityContext->getCurrentUser();
        
        try {
            // Authorization logic...
            
            if (!isset($this->commandPermissions[$commandName])) {
                throw new SecurityConfigurationException(
                    "No security configuration found for command: {$commandName}"
                );
            }
            
            // Permission validation...
            if (isset($config['permission'])) {
                if (!$this->permissionService->hasPermission($user, $config['permission'])) {
                    throw new InsufficientPermissionsException($config['permission']);
                }
            }
            
            // Role validation...
            if (isset($config['roles'])) {
                $hasRequiredRole = false;
                foreach ($config['roles'] as $role) {
                    if ($user->hasRole($role)) {
                        $hasRequiredRole = true;
                        break;
                    }
                }
                if (!$hasRequiredRole) {
                    throw new UnauthorizedException('Insufficient role privileges');
                }
            }
            
            // Execute command
            $this->next->handle($command);
            
        } catch (UnauthenticatedException $e) {
            // Already standardized - re-throw as-is
            throw $e;
        } catch (InsufficientPermissionsException $e) {
            // Convert to generic authorization error for client
            throw $this->errorHandler->handleAuthorizationError(
                $e,
                $user?->getId(),
                $commandName,
                ['required_permission' => $e->getRequiredPermission()]
            );
        } catch (UnauthorizedException $e) {
            // Convert to generic authorization error for client
            throw $this->errorHandler->handleAuthorizationError(
                $e,
                $user?->getId(),
                $commandName
            );
        } catch (SecurityConfigurationException $e) {
            // Log configuration error but don't expose to client
            $this->auditLogger->logSecurityException(
                $user?->getId() ?? 'unknown',
                get_class($e),
                $e->getMessage(),
                ['command' => $commandName]
            );
            
            // Return generic error to client
            throw new UnauthorizedException(SecurityErrorMessages::ACCESS_DENIED);
        }
    }
}
```

#### Error Message Guidelines

**Client-Facing Messages** (Public):
- ✅ `"Invalid credentials"` - Generic authentication failure
- ✅ `"Access denied"` - Generic authorization failure  
- ✅ `"Invalid or expired token"` - Generic token error
- ✅ `"Your session has expired. Please log in again"` - Session timeout
- ❌ `"User not found"` - Reveals user existence
- ❌ `"Password incorrect"` - Reveals user exists but password wrong
- ❌ `"Missing permission: user.delete"` - Reveals internal permission structure

**Server-Side Logging** (Internal):
- ✅ Detailed error messages with full context
- ✅ Stack traces and exception details
- ✅ User enumeration data for security analysis
- ✅ Permission names and roles for audit trails
- ✅ File paths and line numbers for debugging

**HTTP Status Codes**:
- `401 Unauthorized` - Authentication required or failed
- `403 Forbidden` - Authenticated but insufficient permissions
- `400 Bad Request` - Invalid request format
- `429 Too Many Requests` - Rate limiting (when implemented)
- `500 Internal Server Error` - Unexpected security configuration errors

## Security Audit Logging

### Audit Logger Interface

**Purpose**: Provides structured logging for all security-related events to enable monitoring, compliance, and forensic analysis.

```php
interface SecurityAuditLoggerInterface
{
    public function logAuthenticationAttempt(string $username, bool $success, string $method, array $context = []): void;
    public function logAuthorizationCheck(string $userId, string $resource, string $permission, bool $granted, array $context = []): void;
    public function logCommandExecution(string $userId, string $commandName, bool $authorized, array $context = []): void;
    public function logSecurityException(string $userId, string $exceptionType, string $message, array $context = []): void;
    public function logUserSessionActivity(string $userId, string $action, array $context = []): void;
}
```

### Audit Event Types

**Authentication Events**:
- `auth.login.success` - Successful authentication
- `auth.login.failure` - Failed authentication attempt
- `auth.logout` - User logout
- `auth.token.refresh` - Token refresh attempt
- `auth.token.expired` - Token expiration
- `auth.session.timeout` - Session timeout

**Authorization Events**:
- `authz.permission.granted` - Permission check passed
- `authz.permission.denied` - Permission check failed
- `authz.command.authorized` - Command execution authorized
- `authz.command.denied` - Command execution denied
- `authz.role.insufficient` - User lacks required role

**Security Events**:
- `security.exception.authentication` - Authentication exceptions
- `security.exception.authorization` - Authorization exceptions
- `security.config.missing` - Missing security configuration
- `security.context.missing` - Missing security context

### Audit Log Structure

```php
interface AuditEvent
{
    public function getUserId(): ?string;
    public function getEventType(): string;
    public function getEventAction(): string;
    public function getResource(): ?string;
    public function getSuccess(): bool;
    public function getTimestamp(): DateTimeImmutable;
    public function getContext(): array;
    public function getIpAddress(): ?string;
    public function getUserAgent(): ?string;
}
```

**Example Audit Log Entry**:
```json
{
  "timestamp": "2024-01-15T10:30:45Z",
  "event_type": "authz.command.denied",
  "user_id": "mock-user-001",
  "user_email": "user@example.com",
  "resource": "DeleteUserCommand",
  "permission": "user.delete",
  "success": false,
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "context": {
    "required_permission": "user.delete",
    "user_roles": ["user"],
    "command_name": "DeleteUserCommand",
    "error": "InsufficientPermissionsException"
  }
}
```

### Integration with Security Components

#### SecurityCommandDecorator with Audit Logging
```php
class SecurityCommandDecorator implements CommandHandlerInterface
{
    public function __construct(
        private CommandHandlerInterface $next,
        private SecurityContextInterface $securityContext,
        private PermissionServiceInterface $permissionService,
        private SecurityAuditLoggerInterface $auditLogger,
        private array $commandPermissions,
        private array $publicCommands
    ) {}
    
    public function handle(CommandInterface $command): void
    {
        $commandName = get_class($command);
        $user = $this->securityContext->getCurrentUser();
        
        try {
            // Authorization logic...
            
            // Log successful authorization
            $this->auditLogger->logCommandExecution(
                $user?->getId() ?? 'anonymous',
                $commandName,
                true,
                ['command_type' => 'authorized']
            );
            
            $this->next->handle($command);
            
        } catch (SecurityException $e) {
            // Log security failures
            $this->auditLogger->logCommandExecution(
                $user?->getId() ?? 'anonymous',
                $commandName,
                false,
                [
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                    'command_type' => 'denied'
                ]
            );
            
            throw $e;
        }
    }
}
```

#### Authentication Service with Audit Logging
```php
class FileBasedMockAuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private UserProfileRepositoryInterface $profileRepository,
        private SecurityAuditLoggerInterface $auditLogger,
        private string $usersFilePath = null
    ) {}
    
    public function authenticate(string $username, string $password): AuthenticatedUser
    {
        try {
            $users = $this->loadUsersFromFile();
            
            if (!isset($users[$username])) {
                $this->auditLogger->logAuthenticationAttempt(
                    $username,
                    false,
                    'file_based',
                    ['error' => 'user_not_found']
                );
                throw new AuthenticationException('Invalid credentials');
            }
            
            if (!password_verify($password, $users[$username]['password'])) {
                $this->auditLogger->logAuthenticationAttempt(
                    $username,
                    false,
                    'file_based',
                    ['error' => 'invalid_password']
                );
                throw new AuthenticationException('Invalid credentials');
            }
            
            // Successful authentication
            $userId = $users[$username]['id'];
            $profile = $this->profileRepository->findByCognitoId($userId);
            
            $user = new AuthenticatedUser(
                $userId,
                $username,
                $profile ? $profile->getRoles() : [],
                $profile ? $profile->getMetadata() : []
            );
            
            $this->auditLogger->logAuthenticationAttempt(
                $username,
                true,
                'file_based',
                [
                    'user_id' => $userId,
                    'roles' => $user->getRoles()
                ]
            );
            
            return $user;
            
        } catch (Exception $e) {
            $this->auditLogger->logSecurityException(
                $username,
                get_class($e),
                $e->getMessage(),
                ['method' => 'authenticate']
            );
            throw $e;
        }
    }
}
```

### Audit Storage and Monitoring

**Storage Options**:
- **Database**: Structured storage for compliance and reporting
- **Log Files**: File-based logging with log rotation
- **External Services**: Integration with SIEM systems (ELK Stack, Splunk)
- **Event Streaming**: Real-time monitoring with Kafka/RabbitMQ

**Monitoring Alerts**:
- Multiple failed authentication attempts
- Privilege escalation attempts
- Unusual command execution patterns
- Security configuration errors
- Missing audit logs

**Compliance Considerations**:
- **Data Retention**: Configure appropriate log retention periods
- **Data Privacy**: Ensure sensitive data is not logged
- **Integrity**: Protect audit logs from tampering
- **Access Control**: Restrict access to audit logs

## Session and Token Management

### Session Management Strategy

**Purpose**: Define how user sessions and authentication state are managed across different bounded contexts.

#### Context-Specific Session Handling

**Admin Context (Cognito-based)**:
- **Stateless**: Uses JWT tokens, no server-side sessions
- **Token Storage**: Client-side storage (localStorage/sessionStorage/cookies)
- **Expiration**: Token-based expiration (typically 1-24 hours)
- **Refresh**: Refresh tokens for seamless experience

**CustomerPortal Context (FuelPHP-based)**:
- **Stateful**: Server-side sessions with FuelPHP session management
- **Session Storage**: PHP sessions with configurable storage (files/database/Redis)
- **Expiration**: Server-side session timeout configuration
- **Refresh**: Session extension on activity

#### Session Security Interface

```php
interface SessionManagerInterface
{
    public function createSession(AuthenticatedUser $user): string;
    public function validateSession(string $sessionId): ?AuthenticatedUser;
    public function refreshSession(string $sessionId): bool;
    public function invalidateSession(string $sessionId): void;
    public function invalidateAllUserSessions(string $userId): void;
    public function isSessionActive(string $sessionId): bool;
    public function getSessionTimeout(): int;
    public function extendSession(string $sessionId, int $seconds = null): bool;
}
```

#### Admin Context Session Management (Token-based)

```php
class CognitoSessionManager implements SessionManagerInterface
{
    public function __construct(
        private SecurityContextInterface $securityContext,
        private AuthenticationServiceInterface $authService,
        private SecurityAuditLoggerInterface $auditLogger
    ) {}
    
    public function createSession(AuthenticatedUser $user): string
    {
        // Cognito handles token generation
        $token = $this->generateAccessToken($user);
        
        $this->auditLogger->logUserSessionActivity(
            $user->getId(),
            'session.created',
            ['method' => 'cognito_token']
        );
        
        return $token;
    }
    
    public function validateSession(string $sessionId): ?AuthenticatedUser
    {
        try {
            // Validate JWT token with Cognito
            $user = $this->authService->authenticateByToken($sessionId);
            
            $this->auditLogger->logUserSessionActivity(
                $user->getId(),
                'session.validated',
                ['method' => 'cognito_token']
            );
            
            return $user;
        } catch (InvalidTokenException $e) {
            $this->auditLogger->logUserSessionActivity(
                'unknown',
                'session.validation_failed',
                ['error' => $e->getMessage(), 'method' => 'cognito_token']
            );
            return null;
        }
    }
    
    public function invalidateSession(string $sessionId): void
    {
        // Cognito token invalidation (if supported)
        // Or maintain blacklist for immediate invalidation
        $this->addToTokenBlacklist($sessionId);
        
        $this->auditLogger->logUserSessionActivity(
            'unknown',
            'session.invalidated',
            ['method' => 'cognito_token']
        );
    }
}
```

#### CustomerPortal Context Session Management (Server-side)

```php
class FuelPhpSessionManager implements SessionManagerInterface
{
    public function __construct(
        private SecurityAuditLoggerInterface $auditLogger,
        private int $sessionTimeout = 3600
    ) {}
    
    public function createSession(AuthenticatedUser $user): string
    {
        // Use FuelPHP session management
        $sessionId = session_id() ?: session_create_id();
        
        Session::set('user_id', $user->getId());
        Session::set('user_email', $user->getEmail());
        Session::set('user_roles', $user->getRoles());
        Session::set('session_created', time());
        Session::set('last_activity', time());
        
        $this->auditLogger->logUserSessionActivity(
            $user->getId(),
            'session.created',
            [
                'method' => 'fuelphp_session',
                'session_id' => $sessionId,
                'timeout' => $this->sessionTimeout
            ]
        );
        
        return $sessionId;
    }
    
    public function validateSession(string $sessionId): ?AuthenticatedUser
    {
        if (!Session::get('user_id')) {
            return null;
        }
        
        // Check session timeout
        $lastActivity = Session::get('last_activity', 0);
        if (time() - $lastActivity > $this->sessionTimeout) {
            $this->invalidateSession($sessionId);
            
            $this->auditLogger->logUserSessionActivity(
                Session::get('user_id'),
                'session.timeout',
                ['method' => 'fuelphp_session']
            );
            
            return null;
        }
        
        // Update last activity
        Session::set('last_activity', time());
        
        $user = new AuthenticatedUser(
            Session::get('user_id'),
            Session::get('user_email'),
            Session::get('user_roles', [])
        );
        
        return $user;
    }
    
    public function refreshSession(string $sessionId): bool
    {
        if (!$this->isSessionActive($sessionId)) {
            return false;
        }
        
        Session::set('last_activity', time());
        
        $this->auditLogger->logUserSessionActivity(
            Session::get('user_id'),
            'session.refreshed',
            ['method' => 'fuelphp_session']
        );
        
        return true;
    }
    
    public function invalidateSession(string $sessionId): void
    {
        $userId = Session::get('user_id');
        
        Session::destroy();
        
        $this->auditLogger->logUserSessionActivity(
            $userId ?? 'unknown',
            'session.invalidated',
            ['method' => 'fuelphp_session']
        );
    }
    
    public function invalidateAllUserSessions(string $userId): void
    {
        // For FuelPHP, this would require custom session storage
        // to track and invalidate all sessions for a user
        $this->invalidateSession(session_id());
        
        $this->auditLogger->logUserSessionActivity(
            $userId,
            'all_sessions.invalidated',
            ['method' => 'fuelphp_session']
        );
    }
}
```

#### Session Security Considerations

**Session Hijacking Prevention**:
- **Secure Cookie Settings**: HttpOnly, Secure, SameSite attributes
- **Session Regeneration**: Regenerate session ID on authentication
- **IP Address Validation**: Optional IP binding for high-security scenarios
- **User Agent Validation**: Detect session usage from different browsers

**Session Fixation Prevention**:
- **New Session on Login**: Always create new session after authentication
- **Session ID Complexity**: Use cryptographically secure session IDs
- **Session Validation**: Validate session metadata on each request

**Concurrent Session Management**:
- **Session Limits**: Limit number of concurrent sessions per user
- **Session Tracking**: Track active sessions for administrative purposes
- **Force Logout**: Ability to invalidate all user sessions remotely

**Session Storage Security**:
- **Encryption**: Encrypt sensitive session data
- **Secure Storage**: Use secure session storage backends (Redis with encryption)
- **Access Control**: Restrict access to session storage systems

### Token Refresh Implementation

**Purpose**: Implement secure token refresh mechanisms for maintaining user sessions without requiring re-authentication.

#### Token Refresh Interface

```php
interface TokenRefreshServiceInterface
{
    public function refreshAccessToken(string $refreshToken): RefreshResult;
    public function validateRefreshToken(string $refreshToken): bool;
    public function revokeRefreshToken(string $refreshToken): void;
    public function revokeAllRefreshTokens(string $userId): void;
    public function getRefreshTokenExpiry(string $refreshToken): ?DateTimeImmutable;
}

class RefreshResult
{
    public function __construct(
        private string $accessToken,
        private string $refreshToken,
        private DateTimeImmutable $accessTokenExpiry,
        private DateTimeImmutable $refreshTokenExpiry
    ) {}
    
    public function getAccessToken(): string { return $this->accessToken; }
    public function getRefreshToken(): string { return $this->refreshToken; }
    public function getAccessTokenExpiry(): DateTimeImmutable { return $this->accessTokenExpiry; }
    public function getRefreshTokenExpiry(): DateTimeImmutable { return $this->refreshTokenExpiry; }
}
```

#### Admin Context Token Refresh (Cognito)

```php
class CognitoTokenRefreshService implements TokenRefreshServiceInterface
{
    public function __construct(
        private CognitoClient $cognitoClient,
        private SecurityAuditLoggerInterface $auditLogger,
        private UserProfileRepositoryInterface $profileRepository
    ) {}
    
    public function refreshAccessToken(string $refreshToken): RefreshResult
    {
        try {
            // Validate refresh token with Cognito
            if (!$this->validateRefreshToken($refreshToken)) {
                throw new InvalidTokenException('Invalid refresh token');
            }
            
            // Call Cognito refresh token endpoint
            $response = $this->cognitoClient->refreshToken($refreshToken);
            
            $result = new RefreshResult(
                $response['access_token'],
                $response['refresh_token'] ?? $refreshToken, // New refresh token or reuse existing
                new DateTimeImmutable('+' . $response['expires_in'] . ' seconds'),
                new DateTimeImmutable('+30 days') // Refresh token typically longer lived
            );
            
            // Extract user info for audit logging
            $tokenPayload = $this->decodeJwtPayload($result->getAccessToken());
            $userId = $tokenPayload['sub'] ?? 'unknown';
            
            $this->auditLogger->logUserSessionActivity(
                $userId,
                'token.refreshed',
                [
                    'method' => 'cognito',
                    'access_token_expiry' => $result->getAccessTokenExpiry()->format('c'),
                    'refresh_token_expiry' => $result->getRefreshTokenExpiry()->format('c')
                ]
            );
            
            return $result;
            
        } catch (CognitoException $e) {
            $this->auditLogger->logSecurityException(
                'unknown',
                'TokenRefreshException',
                $e->getMessage(),
                ['method' => 'cognito', 'error_code' => $e->getCode()]
            );
            
            throw new InvalidTokenException('Token refresh failed', 0, $e);
        }
    }
    
    public function validateRefreshToken(string $refreshToken): bool
    {
        try {
            // Cognito refresh token validation
            return $this->cognitoClient->validateRefreshToken($refreshToken);
        } catch (CognitoException $e) {
            return false;
        }
    }
    
    public function revokeRefreshToken(string $refreshToken): void
    {
        try {
            $this->cognitoClient->revokeToken($refreshToken);
            
            $this->auditLogger->logUserSessionActivity(
                'unknown',
                'refresh_token.revoked',
                ['method' => 'cognito']
            );
        } catch (CognitoException $e) {
            $this->auditLogger->logSecurityException(
                'unknown',
                'TokenRevocationException',
                $e->getMessage(),
                ['method' => 'cognito']
            );
        }
    }
    
    public function revokeAllRefreshTokens(string $userId): void
    {
        try {
            // Cognito global sign out - invalidates all refresh tokens
            $this->cognitoClient->globalSignOut($userId);
            
            $this->auditLogger->logUserSessionActivity(
                $userId,
                'all_refresh_tokens.revoked',
                ['method' => 'cognito']
            );
        } catch (CognitoException $e) {
            $this->auditLogger->logSecurityException(
                $userId,
                'GlobalSignOutException',
                $e->getMessage(),
                ['method' => 'cognito']
            );
        }
    }
}
```

#### CustomerPortal Context Token Refresh (Session-based)

```php
class FuelPhpTokenRefreshService implements TokenRefreshServiceInterface
{
    public function __construct(
        private SecurityAuditLoggerInterface $auditLogger,
        private SessionManagerInterface $sessionManager
    ) {}
    
    public function refreshAccessToken(string $refreshToken): RefreshResult
    {
        // For session-based authentication, "refresh" means extending session
        if (!$this->sessionManager->refreshSession($refreshToken)) {
            throw new InvalidTokenException('Session refresh failed');
        }
        
        // Return same "token" (session ID) with extended expiry
        $newExpiry = new DateTimeImmutable('+' . $this->sessionManager->getSessionTimeout() . ' seconds');
        
        $result = new RefreshResult(
            $refreshToken, // Session ID acts as both access and refresh token
            $refreshToken,
            $newExpiry,
            $newExpiry
        );
        
        $userId = Session::get('user_id', 'unknown');
        $this->auditLogger->logUserSessionActivity(
            $userId,
            'session.extended',
            [
                'method' => 'fuelphp_session',
                'new_expiry' => $newExpiry->format('c')
            ]
        );
        
        return $result;
    }
    
    public function validateRefreshToken(string $refreshToken): bool
    {
        return $this->sessionManager->isSessionActive($refreshToken);
    }
    
    public function revokeRefreshToken(string $refreshToken): void
    {
        $this->sessionManager->invalidateSession($refreshToken);
    }
    
    public function revokeAllRefreshTokens(string $userId): void
    {
        $this->sessionManager->invalidateAllUserSessions($userId);
    }
}
```

#### Mock Token Refresh Service (Development)

```php
class MockTokenRefreshService implements TokenRefreshServiceInterface
{
    public function __construct(
        private SecurityAuditLoggerInterface $auditLogger,
        private FileBasedMockAuthenticationService $authService
    ) {}
    
    public function refreshAccessToken(string $refreshToken): RefreshResult
    {
        // Decode current mock token
        $payload = $this->decodeMockToken($refreshToken);
        if (!$payload) {
            throw new InvalidTokenException('Invalid refresh token format');
        }
        
        // Check if token is close to expiry (within 5 minutes)
        $currentTime = time();
        $tokenExpiry = $payload['exp'] ?? 0;
        
        if ($tokenExpiry - $currentTime > 300) { // More than 5 minutes left
            throw new InvalidTokenException('Token refresh not required yet');
        }
        
        // Generate new tokens with extended expiry
        $newAccessToken = $this->generateMockToken(
            $payload['sub'],
            $payload['email'],
            3600 // 1 hour
        );
        
        $newRefreshToken = $this->generateMockToken(
            $payload['sub'],
            $payload['email'],
            86400 // 24 hours
        );
        
        $result = new RefreshResult(
            $newAccessToken,
            $newRefreshToken,
            new DateTimeImmutable('+1 hour'),
            new DateTimeImmutable('+24 hours')
        );
        
        $this->auditLogger->logUserSessionActivity(
            $payload['sub'],
            'mock_token.refreshed',
            [
                'method' => 'mock',
                'old_expiry' => date('c', $tokenExpiry),
                'new_expiry' => $result->getAccessTokenExpiry()->format('c')
            ]
        );
        
        return $result;
    }
    
    public function validateRefreshToken(string $refreshToken): bool
    {
        $payload = $this->decodeMockToken($refreshToken);
        if (!$payload) {
            return false;
        }
        
        // Check expiry
        return ($payload['exp'] ?? 0) > time();
    }
    
    private function generateMockToken(string $userId, string $email, int $expiresIn): string
    {
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = base64_encode(json_encode([
            'sub' => $userId,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + $expiresIn
        ]));
        $signature = 'mock-signature';
        
        return "$header.$payload.$signature";
    }
    
    private function decodeMockToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        
        $payload = json_decode(base64_decode($parts[1]), true);
        return $payload ?: null;
    }
}
```

#### Token Refresh Security Considerations

**Refresh Token Security**:
- **Rotation**: Issue new refresh token on each refresh (where supported)
- **Expiration**: Refresh tokens should have longer but limited lifespan
- **Revocation**: Ability to revoke refresh tokens immediately
- **Storage**: Secure storage of refresh tokens (HttpOnly cookies for web)

**Refresh Token Best Practices**:
- **Single Use**: Refresh tokens should be single-use when possible
- **Binding**: Bind refresh tokens to specific clients/devices
- **Rate Limiting**: Limit refresh attempts to prevent abuse
- **Audit Trail**: Log all refresh token activities

**Error Handling**:
- **Token Expiry**: Clear error messages for expired tokens
- **Invalid Tokens**: Consistent error responses for invalid tokens
- **Rate Limiting**: Return appropriate HTTP status codes for rate limits

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
- **Progressive Permissions**: `['user', 'manager']` - Keeps user permissions when promoted
- **Specialized Roles**: `['user', 'report.viewer', 'order.processor']` - Base + specific capabilities
- **Temporal Roles**: `['user', 'temp.admin']` - Temporary elevated access
- **Enhanced Customer Access**: `['customer', 'customer.support']` - Customer with additional support capabilities

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
        // CustomerPortal context roles (separate from admin)
        'customer' => ['account.view', 'account.update'],
        'customer.support' => ['support.ticket.create', 'support.view']
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

// Customer with support access - within CustomerPortal context only
$customerWithSupport = new AuthenticatedUser(
    '3', 
    'customer@example.com', 
    ['customer', 'customer.support'],
    ['company' => 'Acme Corp', 'account_type' => 'Enterprise']
);
// Has: account.view, account.update, support.ticket.create, support.view
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


## Mock Authentication for Development

### FileBasedMockAuthenticationService

**Purpose**: Provides file-based authentication for development environments where external authentication providers (like AWS Cognito) are not available.

**Key Design Principles**:
- **Separation of Concerns**: JSON file handles authentication credentials, database handles profile data
- **Database Consistency**: Uses same `admin_users` table as production for roles and metadata
- **Simple Credentials**: Only stores essential authentication data in JSON format
- **Bootstrap Ready**: Automatically creates default users for new installations

#### JSON File Structure
```json
{
  "admin@example.com": {
    "id": "mock-admin-001",
    "password": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi"
  },
  "manager@example.com": {
    "id": "mock-manager-001", 
    "password": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi"
  },
  "user@example.com": {
    "id": "mock-user-001",
    "password": "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi"
  }
}
```

**Note**: Passwords are hashed using PHP's `password_hash()` function even in development for security best practices.

#### Implementation Pattern
```php
class FileBasedMockAuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(
        private UserProfileRepositoryInterface $profileRepository,
        private string $usersFilePath = null
    ) {}
    
    public function authenticate(string $username, string $password): AuthenticatedUser
    {
        // 1. Validate credentials from JSON file
        $users = $this->loadUsersFromFile();
        if (!isset($users[$username]) || !password_verify($password, $users[$username]['password'])) {
            throw new AuthenticationException('Invalid credentials');
        }
        
        // 2. Get user ID from JSON
        $userId = $users[$username]['id'];
        
        // 3. Fetch profile data from database (same as production)
        $profile = $this->profileRepository->findByCognitoId($userId);
        
        // 4. Create AuthenticatedUser with database profile data
        return new AuthenticatedUser(
            $userId,
            $username,
            $profile ? $profile->getRoles() : [],
            $profile ? $profile->getMetadata() : []
        );
    }
    
    public function authenticateByToken(string $token): AuthenticatedUser
    {
        // 1. Decode and validate mock JWT-like token
        $payload = $this->decodeMockToken($token);
        if (!$payload || $this->isTokenExpired($payload)) {
            throw new AuthenticationException('Invalid or expired token');
        }
        
        // 2. Find user by token's user ID
        $users = $this->loadUsersFromFile();
        $userId = $payload['sub'];
        $user = $this->findUserById($users, $userId);
        
        if (!$user) {
            throw new AuthenticationException('User not found');
        }
        
        // 3. Get profile data from database (same as authenticate())
        $profile = $this->profileRepository->findByCognitoId($userId);
        
        return new AuthenticatedUser(
            $userId,
            $user['email'],
            $profile ? $profile->getRoles() : [],
            $profile ? $profile->getMetadata() : []
        );
    }
    
    public function authenticateBySession(): AuthenticatedUser
    {
        throw new NotSupportedException('Session authentication not supported in mock Cognito service');
    }
}
```

#### Mock Token Implementation

**JWT-like Token Structure**: The mock service generates simple JWT-like tokens for testing token-based authentication flows without requiring actual Cognito infrastructure.

**Token Format**:
```
header.payload.signature
```

**Example Payload**:
```json
{
  "sub": "mock-admin-001",
  "email": "admin@example.com", 
  "iat": 1640995200,
  "exp": 1640998800
}
```

**Token Generation** (for testing):
```php
private function generateMockToken(string $userId, string $email): string
{
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
    $payload = base64_encode(json_encode([
        'sub' => $userId,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + 3600 // 1 hour expiration
    ]));
    $signature = 'mock-signature';
    
    return "$header.$payload.$signature";
}
```

**Token Validation**:
- Decodes JWT-like structure 
- Validates expiration time
- Looks up user by `sub` (subject) claim
- Fetches profile data from database
- Returns AuthenticatedUser with combined data

**Benefits**:
- **Realistic Testing**: Simulates actual Cognito token behavior
- **Expiration Handling**: Tests token expiration logic
- **No Dependencies**: Works without external JWT libraries
- **Development Friendly**: Easy to generate test tokens

#### Benefits
- **No External Dependencies**: Works without Cognito or other external services
- **Database Consistency**: Uses same profile data layer as production
- **Easy Testing**: Simple credential management for development
- **Simple Development**: Easy credential management for testing
- **Human Readable**: JSON format easy to inspect and debug

## Bounded Context Integration

### Core SharedKernel Components
- **Security Context**: Manages authenticated user across all contexts
- **Permission Service**: Unified permission checking logic
- **Security Decorator**: Consistent command authorization
- **Authentication Interface**: Common contract for all auth providers

### Context-Specific Responsibilities
- **Authentication Implementation**: Each bounded context implements `AuthenticationServiceInterface` with appropriate methods
- **User Storage**: Context-specific user profile management (Admin uses `admin_users`, CustomerPortal uses `customer_users`)
- **Permission Configuration**: Context-specific role and permission mappings
- **Session/Token Management**: Context-appropriate authentication state handling (tokens for Cognito in Admin, sessions for FuelPHP in CustomerPortal)

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

#### CustomerPortal Context Profile Wrapper
```php
class CustomerUserProfile
{
    public function __construct(private AuthenticatedUser $user) {}
    
    public function getUser(): AuthenticatedUser { return $this->user; }
    
    public function getCompany(): ?string 
    { 
        return $this->user->getProfileValue('company'); 
    }
    
    public function getAccountType(): ?string 
    { 
        return $this->user->getProfileValue('account_type'); 
    }
    
    public function getSubscriptionStatus(): string 
    { 
        return $this->user->getProfileValue('subscription_status') ?? 'inactive'; 
    }
    
    public function getPreferences(): array 
    { 
        return $this->user->getProfileValue('preferences') ?? []; 
    }
    
    public function getCustomerId(): ?string 
    { 
        return $this->user->getProfileValue('customer_id'); 
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

#### CustomerPortal Context Authentication
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
            
            // 2. Get profile metadata and roles from customer_users database
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
            
            // 2. Get profile and roles from customer_users database
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

#### In CustomerPortal Context Services
```php
class CustomerAccountService
{
    private SecurityContextInterface $securityContext;
    
    public function getUserPreferences(): array
    {
        $user = $this->securityContext->getCurrentUser();
        if (!$user) {
            return [];
        }
        
        $customerProfile = new CustomerUserProfile($user);
        return $customerProfile->getPreferences();
    }
    
    public function getCurrentCustomerId(): ?string
    {
        $user = $this->securityContext->getCurrentUser();
        if (!$user) {
            return null;
        }
        
        $customerProfile = new CustomerUserProfile($user);
        return $customerProfile->getCustomerId();
    }
}
```

### Database Design

#### User Profile Tables
```sql
-- Admin context user profiles (Cognito-based)
CREATE TABLE admin_users (
    cognito_id VARCHAR(36) PRIMARY KEY,  -- Cognito user ID
    roles JSON,                          -- Roles stored in database
    metadata JSON,                       -- Profile metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- CustomerPortal context user profiles (FuelPHP-based)
CREATE TABLE customer_users (
    fuelphp_id INTEGER PRIMARY KEY,      -- FuelPHP user ID
    roles JSON,                          -- Roles stored in database
    metadata JSON,                       -- Profile metadata (company, account_type, preferences, etc.)
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
3. **Security Command Decorator**: Implement configuration-based authorization logic for command execution
4. **Permission Service**: Create configuration-based permission management
5. **Security Context**: Manage authenticated user throughout request lifecycle
6. **Security Exceptions**: Define comprehensive domain-specific security exceptions
7. **Security Audit Logger**: Implement structured logging for all security events
8. **FileBasedMockAuthenticationService**: Development authentication service with hashed password storage
9. **Command Security Configuration**: Centralized security rules for command authorization
10. **Session Management**: Context-specific session handling (token-based for Admin, session-based for CustomerPortal)
11. **Token Refresh Services**: Secure token refresh mechanisms for all authentication contexts
12. **Standardized Error Handling**: Generic client-facing errors with detailed server-side logging
13. **Security Error Handler**: Centralized error message standardization and audit logging
14. **DI Container Integration**: Wire up security components in dependency injection

### Out of Scope
- **Authentication Provider Implementations**: Specific authentication mechanisms (AWS Cognito for Admin, FuelPHP for CustomerPortal) will be implemented by individual bounded contexts
- **Context-Specific Profile Wrappers**: Profile wrapper classes (AdminUserProfile, CustomerUserProfile) will be implemented by individual bounded contexts
- **User Profile Repository Implementations**: Context-specific user profile storage and management (admin_users, customer_users tables)
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