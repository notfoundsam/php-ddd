<?php

use Audience\Admin\Infrastructure\CqrsMessageBus\AdminCommandHandlerRegistry;
use Audience\Admin\Infrastructure\Security\AdminSecurityConfigRegistry;
use Audience\Partner\Infrastructure\CqrsMessageBus\PartnerCommandHandlerRegistry;
use Audience\Site\Infrastructure\CqrsMessageBus\SiteCommandHandlerRegistry;
use Audience\Site\Infrastructure\CqrsMessageBus\SiteQueryHandlerRegistry;
use Audience\Site\Infrastructure\Security\SiteSecurityConfigRegistry;
use Audience\Partner\Infrastructure\Security\PartnerSecurityConfigRegistry;
use DI\ContainerBuilder;
use Fuel\Core\Fuel;
use Infrastructure\CqrsMessageBus\FuelPhpTransactionManager;
use Infrastructure\EventSystem\FuelPhpOutboxRepository;
use Infrastructure\EventSystem\FuelPhpScheduledEventRepository;
use Infrastructure\Http\FuelPhpRequestContext;
use Infrastructure\Notification\FuelPhpEmailNotifier;
use Infrastructure\Security\AdminRememberMeService;
use Infrastructure\Security\AdminUserRepository;
use Infrastructure\Security\FuelPhpSecurityContext;
use Infrastructure\Security\FuelPhpSessionAuthenticator;
use Infrastructure\Security\MysqlRememberTokenRepository;
use Infrastructure\Security\PartnerRememberMeService;
use Infrastructure\Security\PartnerUserRepository;
use Infrastructure\Security\Resolver\AdminSessionResolver;
use Infrastructure\Security\Resolver\PartnerSessionResolver;
use Infrastructure\Security\Resolver\SiteSessionResolver;
use Infrastructure\Security\SiteRememberMeService;
use Infrastructure\Security\SiteUserRepository;
use SharedKernel\Infrastructure\Security\AdminLocalPasswordVerifier;
use SharedKernel\Infrastructure\Security\BcryptPasswordHasher;
use SharedKernel\Infrastructure\Security\PartnerLocalPasswordVerifier;
use SharedKernel\Infrastructure\Security\SiteLocalPasswordVerifier;
use Psr\Container\ContainerInterface;
use SharedKernel\Application\CqrsMessageBus\Commands\CommandBusInterface;
use SharedKernel\Application\CqrsMessageBus\Queries\QueryBusInterface;
use SharedKernel\Application\CqrsMessageBus\TransactionManagerInterface;
use SharedKernel\Application\Http\RequestContextInterface;
use SharedKernel\Application\Throttle\ThrottleConfigResolverInterface;
use SharedKernel\Domain\Cache\CacheInterface;
use SharedKernel\Domain\Environment;
use SharedKernel\Domain\EventSystem\AsyncEventProcessorInterface;
use SharedKernel\Domain\EventSystem\DomainEventCollectorInterface;
use SharedKernel\Domain\EventSystem\EventFactoryInterface;
use SharedKernel\Domain\EventSystem\ListenerProviderInterface;
use SharedKernel\Domain\EventSystem\OutboxEventProcessorInterface;
use SharedKernel\Domain\EventSystem\ScheduledEventProcessorInterface;
use SharedKernel\Domain\Logger\LoggerInterface;
use SharedKernel\Domain\Notification\Channel\EmailNotifierInterface;
use SharedKernel\Domain\Notification\Channel\SmsNotifierInterface;
use SharedKernel\Domain\Redis\RedisClientInterface;
use SharedKernel\Domain\Redis\RedisMasterClientInterface;
use SharedKernel\Domain\Security\PasswordVerifier\AdminPasswordVerifierInterface;
use SharedKernel\Domain\Security\RememberMe\AdminRememberMeServiceInterface;
use SharedKernel\Domain\Security\UserRepository\AdminUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolver\AdminUserResolverInterface;
use SharedKernel\Domain\Security\AuthorizationServiceInterface;
use SharedKernel\Domain\Security\PasswordVerifier\PartnerPasswordVerifierInterface;
use SharedKernel\Domain\Security\RememberMe\PartnerRememberMeServiceInterface;
use SharedKernel\Domain\Security\UserRepository\PartnerUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolver\PartnerUserResolverInterface;
use SharedKernel\Domain\Security\PasswordHasher\PasswordHasherInterface;
use SharedKernel\Domain\Security\RememberMe\RememberTokenRepositoryInterface;
use SharedKernel\Domain\Security\SecurityConfigInterface;
use SharedKernel\Domain\Security\SecurityContextInterface;
use SharedKernel\Domain\Security\SessionAuthenticator\SessionAuthenticatorInterface;
use SharedKernel\Domain\Security\PasswordVerifier\SitePasswordVerifierInterface;
use SharedKernel\Domain\Security\RememberMe\SiteRememberMeServiceInterface;
use SharedKernel\Domain\Security\UserRepository\SiteUserRepositoryInterface;
use SharedKernel\Domain\Security\UserResolver\SiteUserResolverInterface;
use SharedKernel\Domain\Storage\StorageInterface;
use SharedKernel\Domain\Throttle\ThrottleFactoryInterface;
use SharedKernel\Infrastructure\Cache\CacheFactory;
use SharedKernel\Infrastructure\CqrsMessageBus\CommandBusFactory;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandLoggerDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandThrottleDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\CommandTransactionDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\QueryLoggerDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\QueryThrottleDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\SecurityCommandDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\Decorators\SecurityQueryDecorator;
use SharedKernel\Infrastructure\CqrsMessageBus\QueryBusFactory;
use SharedKernel\Infrastructure\Security\ConfigAuthorizationService;
use SharedKernel\Infrastructure\Security\SecurityConfigFactory;
use SharedKernel\Infrastructure\EventSystem\DomainEventCollector;
use SharedKernel\Infrastructure\EventSystem\EventFactoryFactory;
use SharedKernel\Infrastructure\EventSystem\ListenerProviderFactory;
use SharedKernel\Infrastructure\EventSystem\OutboxEventProcessor;
use SharedKernel\Infrastructure\EventSystem\ScheduledEventProcessor;
use Infrastructure\EventSystem\SqsAsyncEventProcessorFactory;
use Infrastructure\Logger\MonologLoggerFactory;
use SharedKernel\Infrastructure\Notification\Email\SenderRegistry;
use Infrastructure\Notification\SmsNotifierFactory;
use Infrastructure\Redis\RedisClientFactory;
use Infrastructure\Redis\RedisMasterClientFactory;
use Infrastructure\Storage\StorageFactory;
use SharedKernel\Infrastructure\Storage\CdnUrlResolver;
use SharedKernel\Infrastructure\Throttle\ThrottleConfigDefaults;
use SharedKernel\Infrastructure\Throttle\ThrottleConfigResolver;
use SharedKernel\Infrastructure\Throttle\ThrottleDriverFactory;

$containerBuilder = new ContainerBuilder();
$repositories = require APPPATH . 'config/repositories.php';

if (Fuel::$env !== Fuel::DEVELOPMENT && Fuel::$env !== Fuel::TEST) {
    $containerBuilder->enableCompilation(APPPATH . '/tmp');
    $containerBuilder->writeProxiesToFile(true, APPPATH . '/tmp/proxies');
}

$containerBuilder->addDefinitions(array_merge([
    Environment::class => DI\autowire(Environment::class)->constructor(Fuel::$env, getenv('TEST_ENV_ID') ?: null),
    LoggerInterface::class => DI\factory(MonologLoggerFactory::class),
    RedisClientInterface::class => DI\factory(RedisClientFactory::class),
    RedisMasterClientInterface::class => DI\factory(RedisMasterClientFactory::class),
    CacheInterface::class => DI\factory(CacheFactory::class),
    CdnUrlResolver::class => DI\autowire(CdnUrlResolver::class)->constructor([
        'public/images/' => getenv('CDN_IMAGES_URL') ?: '',
    ]),
    StorageInterface::class => DI\factory(StorageFactory::class),

    // Security & Request Context
    SecurityContextInterface::class => DI\autowire(FuelPhpSecurityContext::class),
    RequestContextInterface::class => DI\autowire(FuelPhpRequestContext::class),

    // Authentication: password hashing, per-audience verifiers, sessions, remember-me.
    PasswordHasherInterface::class => DI\autowire(BcryptPasswordHasher::class),

    AdminUserRepositoryInterface::class => DI\autowire(AdminUserRepository::class),
    PartnerUserRepositoryInterface::class => DI\autowire(PartnerUserRepository::class),
    SiteUserRepositoryInterface::class => DI\autowire(SiteUserRepository::class),

    AdminPasswordVerifierInterface::class => DI\autowire(AdminLocalPasswordVerifier::class),
    PartnerPasswordVerifierInterface::class => DI\autowire(PartnerLocalPasswordVerifier::class),
    SitePasswordVerifierInterface::class => DI\autowire(SiteLocalPasswordVerifier::class),

    SessionAuthenticatorInterface::class => DI\autowire(FuelPhpSessionAuthenticator::class),

    RememberTokenRepositoryInterface::class => DI\autowire(MysqlRememberTokenRepository::class),
    AdminRememberMeServiceInterface::class => DI\autowire(AdminRememberMeService::class),
    PartnerRememberMeServiceInterface::class => DI\autowire(PartnerRememberMeService::class),
    SiteRememberMeServiceInterface::class => DI\autowire(SiteRememberMeService::class),

    AdminUserResolverInterface::class => DI\autowire(AdminSessionResolver::class),
    PartnerUserResolverInterface::class => DI\autowire(PartnerSessionResolver::class),
    SiteUserResolverInterface::class => DI\autowire(SiteSessionResolver::class),

    // Security Config — composed from SharedKernel + per-audience registries.
    // Bounded contexts (Crm, Marketing) own only domain. Audiences (Admin, Partner, Site)
    // own their commands, queries, and roles. Add new audience registries here.
    SecurityConfigInterface::class => DI\factory(function (ContainerInterface $c) {
        $factory = new SecurityConfigFactory([
            $c->get(AdminSecurityConfigRegistry::class),
            $c->get(PartnerSecurityConfigRegistry::class),
            $c->get(SiteSecurityConfigRegistry::class),
        ]);
        return $factory();
    }),
    // Depends on SecurityConfigInterface (bound via factory above).
    AuthorizationServiceInterface::class => DI\autowire(ConfigAuthorizationService::class),

    // Transactions (CQRS chain)
    TransactionManagerInterface::class => DI\autowire(FuelPhpTransactionManager::class),

    // Throttle
    ThrottleFactoryInterface::class => DI\factory(ThrottleDriverFactory::class),
    ThrottleConfigResolverInterface::class => DI\factory(function () {
        return new ThrottleConfigResolver(ThrottleConfigDefaults::load());
    }),

    // CQRS Message Bus
    // Decorator chain (outermost → innermost): Throttle → Security → Logger → Transaction → Handler
    CommandBusInterface::class => DI\factory(function (ContainerInterface $c) {
        $bus = (new CommandBusFactory($c, [
            new AdminCommandHandlerRegistry(),
            new PartnerCommandHandlerRegistry(),
            new SiteCommandHandlerRegistry(),
        ]))();
        $bus = new CommandTransactionDecorator(
            $bus,
            $c->get(DomainEventCollectorInterface::class),
            $c->get(TransactionManagerInterface::class),
            $c->get(OutboxEventProcessorInterface::class)
        );
        $bus = new CommandLoggerDecorator($bus, $c->get(LoggerInterface::class));
        $bus = new SecurityCommandDecorator(
            $bus,
            $c->get(SecurityContextInterface::class),
            $c->get(AuthorizationServiceInterface::class),
            $c->get(SecurityConfigInterface::class)
        );
        return new CommandThrottleDecorator(
            $bus,
            $c->get(ThrottleFactoryInterface::class),
            $c->get(ThrottleConfigResolverInterface::class),
            $c->get(SecurityContextInterface::class),
            $c->get(RequestContextInterface::class),
            $c->get(LoggerInterface::class)
        );
    }),
    // Decorator chain (outermost → innermost): Throttle → Security → Logger → Handler
    QueryBusInterface::class => DI\factory(function (ContainerInterface $c) {
        $bus = (new QueryBusFactory($c, [
            new SiteQueryHandlerRegistry(),
        ]))();
        $bus = new QueryLoggerDecorator($bus, $c->get(LoggerInterface::class));
        $bus = new SecurityQueryDecorator(
            $bus,
            $c->get(SecurityContextInterface::class),
            $c->get(AuthorizationServiceInterface::class),
            $c->get(SecurityConfigInterface::class)
        );
        return new QueryThrottleDecorator(
            $bus,
            $c->get(ThrottleFactoryInterface::class),
            $c->get(ThrottleConfigResolverInterface::class),
            $c->get(SecurityContextInterface::class),
            $c->get(RequestContextInterface::class),
            $c->get(LoggerInterface::class)
        );
    }),

    // Event System
    EventFactoryInterface::class => DI\factory(EventFactoryFactory::class),
    ListenerProviderInterface::class => DI\factory(ListenerProviderFactory::class),
    DomainEventCollectorInterface::class => DI\autowire(DomainEventCollector::class),

    // Event Repositories (FuelPHP-specific)
    FuelPhpOutboxRepository::class => DI\autowire(FuelPhpOutboxRepository::class),
    FuelPhpScheduledEventRepository::class => DI\autowire(FuelPhpScheduledEventRepository::class),

    // Event Processors (wired explicitly to their repositories)
    OutboxEventProcessorInterface::class => DI\autowire(OutboxEventProcessor::class)->constructor(
        DI\get(FuelPhpOutboxRepository::class),
        DI\get(ListenerProviderInterface::class),
        DI\get(LoggerInterface::class)
    ),
    ScheduledEventProcessorInterface::class => DI\autowire(ScheduledEventProcessor::class)->constructor(
        DI\get(FuelPhpScheduledEventRepository::class),
        DI\get(ListenerProviderInterface::class),
        DI\get(LoggerInterface::class)
    ),
    AsyncEventProcessorInterface::class => DI\factory(SqsAsyncEventProcessorFactory::class),

    // Notification
    SenderRegistry::class => DI\autowire(SenderRegistry::class)->constructor(
        getenv('EMAIL_DOMAIN') ?: 'php-ddd.test'
    ),
    EmailNotifierInterface::class => DI\autowire(FuelPhpEmailNotifier::class),
    SmsNotifierInterface::class => DI\factory(SmsNotifierFactory::class),
]), $repositories);

return $containerBuilder->build();
