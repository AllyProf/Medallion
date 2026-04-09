<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = \App\Models\MenuItem::create(['name' => 'Human Resources', 'slug' => 'hr', 'icon' => 'fa-users', 'route' => null, 'is_active' => true, 'sort_order' => 6]);
\App\Models\MenuItem::create(['name' => 'Payroll', 'slug' => 'hr-payroll', 'icon' => 'fa-money', 'route' => 'hr.payroll', 'parent_id' => \->id, 'is_active' => true, 'sort_order' => 1]);
\App\Models\MenuItem::create(['name' => 'Dashboard', 'slug' => 'hr-dashboard', 'icon' => 'fa-dashboard', 'route' => 'hr.dashboard', 'parent_id' => \->id, 'is_active' => true, 'sort_order' => 0]);
echo 'Done';

