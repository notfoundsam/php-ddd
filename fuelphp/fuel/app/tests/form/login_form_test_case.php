<?php

namespace Fuel\Core;

use LogicException;
use ReflectionClass;

abstract class Test_Login_Form_TestCase extends TestCase
{
    // FuelPHP caches Fieldsets in Fieldset::$_instances. php-fpm wipes userland statics
    // between requests, so this never bites in production — but a single PHPUnit process
    // runs every test in one PHP context, and the second forge() with the same name throws.
    public function setUp(): void
    {
        $prop = (new ReflectionClass(Fieldset::class))->getProperty('_instances');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    /** @return class-string */
    abstract protected function formClass(): string;

    /** @return class-string */
    abstract protected function commandClass(): string;

    /**
     * @param array<string, mixed> $raw
     * @return mixed
     */
    private function build(array $raw)
    {
        $class = $this->formClass();
        return $class::fromHttpInput($raw);
    }

    public function test_valid_input_builds_command_with_typed_vos(): void
    {
        $form = $this->build([
            'email' => 'User@Example.com',
            'password' => 'secret',
            'remember' => '1',
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame([], $form->errors());

        $cmd = $form->toCommand();
        $this->assertInstanceOf($this->commandClass(), $cmd);
        $this->assertSame('user@example.com', $cmd->getEmail()->getEmail());
        $this->assertSame('secret', $cmd->getPassword()->value());
        $this->assertTrue($cmd->isRemember());
    }

    public function test_email_with_surrounding_whitespace_is_accepted(): void
    {
        $form = $this->build([
            'email' => '  user@example.com  ',
            'password' => 'secret',
        ]);

        $this->assertTrue($form->isValid());
        $this->assertSame('user@example.com', $form->email());
        $this->assertSame('user@example.com', $form->toCommand()->getEmail()->getEmail());
    }

    public function test_missing_remember_defaults_to_false(): void
    {
        $cmd = $this->build([
            'email' => 'a@b.co',
            'password' => 'x',
        ])->toCommand();
        $this->assertFalse($cmd->isRemember());
    }

    public function test_missing_email_produces_required_error(): void
    {
        $form = $this->build(['password' => 'secret']);
        $this->assertFalse($form->isValid());
        $this->assertArrayHasKey('email', $form->errors());
    }

    public function test_invalid_email_format_produces_error(): void
    {
        $form = $this->build([
            'email' => 'not-an-email',
            'password' => 'secret',
        ]);
        $this->assertFalse($form->isValid());
        $this->assertArrayHasKey('email', $form->errors());
    }

    public function test_password_exceeding_72_chars_produces_error(): void
    {
        $form = $this->build([
            'email' => 'a@b.co',
            'password' => str_repeat('a', 73),
        ]);
        $this->assertFalse($form->isValid());
        $this->assertArrayHasKey('password', $form->errors());
    }

    public function test_raw_email_is_returned_for_re_render_on_error(): void
    {
        $form = $this->build([
            'email' => 'kept-as-is@example.com',
            'password' => '',
        ]);
        $this->assertFalse($form->isValid());
        $this->assertSame('kept-as-is@example.com', $form->email());
    }

    public function test_to_command_throws_on_invalid_form(): void
    {
        $form = $this->build([]);
        $this->expectException(LogicException::class);
        $form->toCommand();
    }
}
