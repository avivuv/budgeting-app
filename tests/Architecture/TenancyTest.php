<?php

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| Multi-tenancy guards (docs/03-multi-tenancy.md).
*/

it('scopes every model whose table has a family_id', function () {
    $missing = [];

    foreach (File::allFiles(app_path('Models')) as $file) {
        $class = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        /** @var Model $model */
        $model = new $class;

        // The activity log lives in another database and is filtered explicitly by family_id.
        // Membership pivots (family_user) link users to families and are read across families.
        if ($model->getConnectionName() === 'activity' || $model instanceof Pivot) {
            continue;
        }

        $hasFamilyColumn = Schema::hasColumn($model->getTable(), 'family_id');
        $usesTrait = in_array(BelongsToFamily::class, class_uses_recursive($class), true);

        if ($hasFamilyColumn && ! $usesTrait) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([]);
});

it('bypasses the family scope only in tenant resolvers and admin repositories [BR-I07]', function () {
    $violations = [];

    foreach (File::allFiles(app_path()) as $file) {
        $code = $file->getContents();

        if (! Str::contains($code, 'withoutGlobalScope(FamilyScope') && ! Str::contains($code, 'withoutGlobalScopes(')) {
            continue;
        }

        $relative = str_replace('\\', '/', $file->getRelativePathname());

        if (Str::startsWith($relative, 'Repositories/Eloquent/Admin/')) {
            continue;
        }

        $offset = 0;
        while (($position = strpos($code, 'withoutGlobalScope', $offset)) !== false) {
            preg_match_all('/function\s+(\w+)\s*\(/', substr($code, 0, $position), $functions);
            $method = end($functions[1]) ?: '(none)';

            if (! Str::startsWith($method, 'resolve')) {
                $violations[] = "{$relative}::{$method}";
            }

            $offset = $position + 1;
        }
    }

    expect($violations)->toBe([]);
});
