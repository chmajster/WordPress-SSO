<?php

declare(strict_types=1);

use Chmajster\PlanWordPressSSO\Roles;
use PHPUnit\Framework\TestCase;

final class RolesTest extends TestCase
{
    public function testRoleMapping(): void
    {
        self::assertSame('admin', Roles::mapRoles(['administrator']));
        self::assertSame('leader', Roles::mapRoles(['plan_leader']));
        self::assertSame('deputy', Roles::mapRoles(['plan_deputy']));
        self::assertSame('volunteer', Roles::mapRoles(['plan_volunteer']));
        self::assertNull(Roles::mapRoles(['subscriber']));
    }

    public function testRolePriority(): void
    {
        self::assertSame(
            'admin',
            Roles::mapRoles(['plan_volunteer', 'plan_deputy', 'plan_leader', 'administrator'])
        );
        self::assertSame(
            'leader',
            Roles::mapRoles(['plan_volunteer', 'plan_deputy', 'plan_leader'])
        );
        self::assertSame(
            'deputy',
            Roles::mapRoles(['plan_volunteer', 'plan_deputy'])
        );
    }
}
