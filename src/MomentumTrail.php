<?php

namespace Bellows\Plugins;

use Bellows\PluginSdk\Contracts\Deployable;
use Bellows\PluginSdk\Contracts\Installable;
use Bellows\PluginSdk\Facades\Artisan;
use Bellows\PluginSdk\Facades\Deployment;
use Bellows\PluginSdk\Facades\DeployScript;
use Bellows\PluginSdk\Facades\Project;
use Bellows\PluginSdk\Facades\Vite;
use Bellows\PluginSdk\Plugin;
use Bellows\PluginSdk\PluginResults\CanBeDeployed;
use Bellows\PluginSdk\PluginResults\CanBeInstalled;
use Bellows\PluginSdk\PluginResults\DeploymentResult;
use Bellows\PluginSdk\PluginResults\InstallationResult;

class MomentumTrail extends Plugin implements Deployable, Installable
{
    use CanBeDeployed, CanBeInstalled;

    public function install(): ?InstallationResult
    {
        return InstallationResult::create()
            ->npmDevPackage('vite-plugin-watch')
            ->wrapUp($this->installationWrapUp(...))
            ->updateConfigs([
                'trail.output.routes'     => "resource_path('js/routes.json')",
                'trail.output.typescript' => "resource_path('types/routes.d.ts')",
            ])
            ->gitIgnore('resources/js/routes.json')
            ->publishTag('trail-config');
    }

    public function deploy(): ?DeploymentResult
    {
        return DeploymentResult::create()->updateDeployScript(
            fn () => DeployScript::addAfterComposerInstall(
                [
                    <<<'SCRIPT'
                    if [ ! -f resources/js/routes.json ]; then
                        echo "Creating resources/js/routes.json"
                        echo "{}" > resources/js/routes.json
                    fi
                    SCRIPT,
                    <<<'SCRIPT'
                    if [ ! -d resources/types ]; then
                        echo "Creating resources/types"
                        mkdir resources/types
                    fi
                    SCRIPT,
                    Artisan::inDeployScript('trail:generate'),
                ],
            )
        );
    }

    public function requiredNpmPackages(): array
    {
        return [
            'momentum-trail',
        ];
    }

    public function requiredComposerPackages(): array
    {
        return [
            'based/momentum-trail',
        ];
    }

    public function shouldDeploy(): bool
    {
        return !Deployment::site()->isInDeploymentScript('trail:generate');
    }

    protected function installationWrapUp(): void
    {
        if (!Project::file('resources/js/routes.json')->exists()) {
            Project::file('resources/js/routes.json')->write('{}');
        }

        // TODO: dir() method on Project (just maps to file but feels more natural)
        if (!Project::file('resources/types')->isDirectory()) {
            Project::file('resources/types')->makeDirectory();
        }

        Project::file('vite.config.js')->addJsImport("import { watch } from 'vite-plugin-watch'");

        Vite::addPlugin(<<<'PLUGIN'
        watch({
            pattern: 'routes/*.php',
            command: 'php artisan trail:generate',
        })
        PLUGIN);

        Project::file('resources/js/app.ts')
            ->addJsImport([
                "import { trail } from 'momentum-trail'",
                "import routes from '@/routes.json'",
            ])
            ->replace(
                '.use(ZiggyVue, Ziggy)',
                ".use(ZiggyVue, Ziggy)\n.use(trail, { routes })"
            );
    }
}
