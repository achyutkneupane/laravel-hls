<?php

declare(strict_types=1);

arch('no dd, dump, or ray calls')
    ->expect(['dd', 'dump', 'ray'])
    ->each
    ->not
    ->toBeUsed();

arch('traits are of type trait')
    ->expect('AchyutN\LaravelHLS\Traits')
    ->toBeTraits();

arch('all classes are final')
    ->expect('AchyutN\FilamentLogViewer')
    ->classes()
    ->toBeFinal();
