<?php

use Bellows\Plugins\MomentumTrail;

it('can update the deploy script', function () {
    $result = $this->plugin(MomentumTrail::class)->deploy();

    expect($result->getUpdateDeployScript())->toContain('trail:generate');
    expect($result->getUpdateDeployScript())->toContain('if [ ! -f resources/js/routes.json ]; then');
});
