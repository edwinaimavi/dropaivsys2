<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RolePermissionPresentationService
{
    public const FALLBACK_MODULE = 'other';

    public function present(Collection $permissions): array
    {
        $definitions = config('permission_groups.modules', []);
        $groups = [];
        $unclassified = [];

        foreach ($permissions->sortBy('id') as $permission) {
            [$moduleKey, $subgroupKey] = $this->classificationFor($permission->name, $definitions);
            if ($moduleKey === self::FALLBACK_MODULE) {
                $unclassified[] = $permission->name;
            }

            $module = $moduleKey === self::FALLBACK_MODULE
                ? ['label' => 'Otros / Sin clasificar', 'icon' => 'fas fa-question-circle', 'order' => 999, 'subgroups' => [
                    'unclassified' => ['label' => 'Permisos sin clasificar', 'order' => 999],
                ]]
                : $definitions[$moduleKey];
            $subgroup = $module['subgroups'][$subgroupKey];

            $groups[$moduleKey] ??= [
                'key' => $moduleKey, 'label' => $module['label'], 'icon' => $module['icon'],
                'order' => $module['order'], 'total' => 0, 'subgroups' => [],
            ];
            $groups[$moduleKey]['subgroups'][$subgroupKey] ??= [
                'key' => $subgroupKey, 'label' => $subgroup['label'], 'order' => $subgroup['order'],
                'total' => 0, 'permissions' => [],
            ];

            $displayLabel = (config('permission_groups.labels', [])[$permission->name] ?? null)
                ?: ($permission->description ?: Str::headline($permission->name));
            $routeName = Route::has($permission->name) ? $permission->name : null;
            $groups[$moduleKey]['subgroups'][$subgroupKey]['permissions'][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard' => $permission->guard_name,
                'label' => $displayLabel,
                'route_name' => $routeName,
                'search_text' => implode(' ', array_filter([
                    $displayLabel, $permission->name, $routeName, $module['label'], $subgroup['label'], $permission->guard_name,
                ])),
            ];
            $groups[$moduleKey]['total']++;
            $groups[$moduleKey]['subgroups'][$subgroupKey]['total']++;
        }

        foreach ($groups as &$module) {
            uasort($module['subgroups'], fn ($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);
        }
        unset($module);
        uasort($groups, fn ($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        return ['modules' => array_values($groups), 'unclassified' => $unclassified, 'total' => $permissions->count()];
    }

    private function classificationFor(string $permissionName, array $definitions): array
    {
        $bestMatch = null;
        foreach ($definitions as $moduleKey => $module) {
            foreach ($module['subgroups'] as $subgroupKey => $subgroup) {
                foreach ($subgroup['prefixes'] as $prefix) {
                    if (str_starts_with($permissionName, $prefix)) {
                        if ($bestMatch === null || strlen($prefix) > $bestMatch['length']) {
                            $bestMatch = ['module' => $moduleKey, 'subgroup' => $subgroupKey, 'length' => strlen($prefix)];
                        }
                    }
                }
            }
        }

        return $bestMatch
            ? [$bestMatch['module'], $bestMatch['subgroup']]
            : [self::FALLBACK_MODULE, 'unclassified'];
    }
}
