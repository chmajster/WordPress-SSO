<?php

declare(strict_types=1);

namespace Chmajster\PlanWordPressSSO;

final class Roles
{
    /** @var array<string, string> */
    private const MAP = [
        'administrator' => 'admin',
        'plan_leader' => 'leader',
        'plan_deputy' => 'deputy',
        'plan_volunteer' => 'volunteer',
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'plan_volunteer' => 'Plan — Wolontariusz',
        'plan_leader' => 'Plan — Leader',
        'plan_deputy' => 'Plan — Zastępca',
    ];

    public static function activate(): void
    {
        foreach (self::LABELS as $role => $label) {
            add_role($role, $label, ['read' => true]);
        }
    }

    /**
     * @param array<int, mixed> $roles
     */
    public static function mapRoles(array $roles): ?string
    {
        $roles = array_values(array_filter(array_map('strval', $roles)));

        foreach (self::MAP as $wordpressRole => $planRole) {
            if (in_array($wordpressRole, $roles, true)) {
                return $planRole;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function mapping(): array
    {
        return self::MAP;
    }
}
