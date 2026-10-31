<?php

declare(strict_types=1);

namespace App\Support\Inertia;

use Illuminate\View\FileViewFinder;

/**
 * Locates Inertia pages the same way resources/js/app.ts resolves them:
 * "transactions/Index" => resources/js/modules/transactions/pages/Index.vue,
 * falling back to resources/js/pages/<name> for starter-kit pages.
 */
class ModulePageFinder extends FileViewFinder
{
    public function find($name)
    {
        $parts = explode('/', $name, 2);

        if (count($parts) === 2) {
            [$feature, $page] = $parts;

            foreach ($this->extensions as $extension) {
                $path = resource_path("js/modules/{$feature}/pages/{$page}.{$extension}");

                if ($this->files->exists($path)) {
                    return $path;
                }
            }
        }

        return parent::find($name);
    }
}
