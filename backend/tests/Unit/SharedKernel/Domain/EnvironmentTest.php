<?php

declare(strict_types=1);

namespace Tests\Unit\SharedKernel\Domain;

use PHPUnit\Framework\TestCase;
use SharedKernel\Domain\Environment;

class EnvironmentTest extends TestCase
{
    public function testConstructorWithoutTestEnvironmentId(): void
    {
        $environment = new Environment('production');

        $this->assertEquals('production', $environment->getValue());
        $this->assertNull($environment->getTestEnvironmentId());
        $this->assertFalse($environment->hasTestEnvironmentId());
    }

    public function testConstructorWithTestEnvironmentId(): void
    {
        $environment = new Environment('staging', '1234');

        $this->assertEquals('staging', $environment->getValue());
        $this->assertEquals('1234', $environment->getTestEnvironmentId());
        $this->assertTrue($environment->hasTestEnvironmentId());
    }

    public function testConstructorWithNullTestEnvironmentId(): void
    {
        $environment = new Environment('test', null);

        $this->assertEquals('test', $environment->getValue());
        $this->assertNull($environment->getTestEnvironmentId());
        $this->assertFalse($environment->hasTestEnvironmentId());
    }

    public function testConstructorWithEmptyStringTestEnvironmentId(): void
    {
        $environment = new Environment('test', '');

        $this->assertEquals('test', $environment->getValue());
        $this->assertEquals('', $environment->getTestEnvironmentId());
        $this->assertFalse($environment->hasTestEnvironmentId());
    }

    public function testHasTestEnvironmentIdReturnsTrueForNonEmptyValue(): void
    {
        $environment = new Environment('staging', 'abc123');

        $this->assertTrue($environment->hasTestEnvironmentId());
    }

    public function testHasTestEnvironmentIdReturnsFalseForNull(): void
    {
        $environment = new Environment('production', null);

        $this->assertFalse($environment->hasTestEnvironmentId());
    }

    public function testHasTestEnvironmentIdReturnsFalseForEmptyString(): void
    {
        $environment = new Environment('test', '');

        $this->assertFalse($environment->hasTestEnvironmentId());
    }

    public function testGetTestEnvironmentIdReturnsValue(): void
    {
        $environment = new Environment('staging', 'my-test-id');

        $this->assertEquals('my-test-id', $environment->getTestEnvironmentId());
    }

    public function testIsProduction(): void
    {
        $environment = new Environment('production');

        $this->assertTrue($environment->isProduction());
        $this->assertFalse($environment->isStaging());
        $this->assertFalse($environment->isTest());
        $this->assertFalse($environment->isDevelopment());
    }

    public function testIsStaging(): void
    {
        $environment = new Environment('staging');

        $this->assertFalse($environment->isProduction());
        $this->assertTrue($environment->isStaging());
        $this->assertFalse($environment->isTest());
        $this->assertFalse($environment->isDevelopment());
        $this->assertTrue($environment->isStagingLike());
    }

    public function testIsTest(): void
    {
        $environment = new Environment('test');

        $this->assertFalse($environment->isProduction());
        $this->assertFalse($environment->isStaging());
        $this->assertTrue($environment->isTest());
        $this->assertFalse($environment->isDevelopment());
        $this->assertTrue($environment->isStagingLike());
    }

    public function testIsDevelopment(): void
    {
        $environment = new Environment('development');

        $this->assertFalse($environment->isProduction());
        $this->assertFalse($environment->isStaging());
        $this->assertFalse($environment->isTest());
        $this->assertTrue($environment->isDevelopment());
        $this->assertFalse($environment->isStagingLike());
    }

    public function testIsStagingLikeForStaging(): void
    {
        $environment = new Environment('staging');

        $this->assertTrue($environment->isStagingLike());
    }

    public function testIsStagingLikeForTest(): void
    {
        $environment = new Environment('test');

        $this->assertTrue($environment->isStagingLike());
    }

    public function testIsStagingLikeForProduction(): void
    {
        $environment = new Environment('production');

        $this->assertFalse($environment->isStagingLike());
    }

    public function testIsCloudLike(): void
    {
        $this->assertTrue((new Environment('production'))->isCloudLike());
        $this->assertTrue((new Environment('staging'))->isCloudLike());
        $this->assertTrue((new Environment('test'))->isCloudLike());
        $this->assertFalse((new Environment('development'))->isCloudLike());
        $this->assertFalse((new Environment('local'))->isCloudLike());
    }

    public function testEquals(): void
    {
        $env1 = new Environment('production');
        $env2 = new Environment('production');
        $env3 = new Environment('staging');

        $this->assertTrue($env1->equals($env2));
        $this->assertFalse($env1->equals($env3));
    }
}
