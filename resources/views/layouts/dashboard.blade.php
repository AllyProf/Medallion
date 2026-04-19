<!DOCTYPE html>
<html lang="en">
  <head>
    <meta name="description" content="MEDALLION - Point of Sale System for Business">
    <title>@yield('title', 'Dashboard') - MEDALLION</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Main CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('css/admin.css') }}">
    <!-- Font-icon css-->
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
      /* Prevent Flash of Unstyled Content (FOUC) */
      body {
        visibility: hidden;
        opacity: 0;
      }
      body.loaded {
        visibility: visible;
        opacity: 1;
        transition: opacity 0.3s ease-in-out;
      }
      :root {
        --primary: #940000;
        --secondary: #000000;
        --font-family: "Century Gothic", "Apple Gothic", "ITC Century Gothic", sans-serif;
      }
      body, h1, h2, h3, h4, h5, h6, p, div, span, a, li, input, button, label, td, th {
        font-family: var(--font-family) !important;
      }
      .fa, .fas, .far, .fab, .icon, [class^="fa-"], [class*=" fa-"], i, .app-sidebar__toggle {
        font-family: FontAwesome !important;
      }
      .app-header__logo {
        font-family: var(--font-family) !important;
        font-weight: 700;
      }
      .menu-separator {
        padding: 8px 20px;
        margin-top: 8px;
        margin-bottom: 4px;
        border-top: 1px solid #e0e0e0;
        list-style: none;
        pointer-events: none;
        cursor: default;
      }
      .menu-separator a {
        pointer-events: none;
        cursor: default;
      }
      .menu-separator-content {
        display: flex;
        align-items: center;
        color: #666;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.7;
        pointer-events: none;
        cursor: default;
      }
      .menu-separator-content i {
        margin-right: 8px;
        font-size: 11px;
        color: #666;
      }
      .menu-separator-label {
        color: #666;
      }
      /* Submenu spacing and indentation */
      .treeview-menu {
        padding-left: 0;
      }
      .treeview-menu > li {
        margin-bottom: 4px;
        padding-left: 20px;
      }
      .treeview-menu > li:last-child {
        margin-bottom: 0;
      }
      .treeview-item {
        padding-left: 8px;
        display: flex;
        align-items: center;
      }
      .treeview-item .icon {
        margin-right: 8px;
        width: 16px;
        text-align: center;
      }
      .btn-primary, .widget-small.primary.coloured-icon {
        background-color: #940000;
        border-color: #940000;
      }
      .btn-primary:hover {
        background-color: #7a0000;
        border-color: #7a0000;
      }
      .app-header__logo {
        color: #fff;
      }
      .app-sidebar__user {
        background: #222d32; /* Neutral dark background */
        border-bottom: 1px solid rgba(255,255,255,0.05);
      }
    </style>
    @stack('styles')
  </head>
  <body class="app sidebar-mini">
    @php
      // Get dashboard URL based on user type
      $dashboardUrl = route('dashboard');
      if (session('is_staff') && session('staff_role_id')) {
        $staffRole = \App\Models\Role::find(session('staff_role_id'));
        if ($staffRole) {
          $roleSlug = \Illuminate\Support\Str::slug($staffRole->name);
          $dashboardUrl = route('dashboard.role', ['role' => $roleSlug]);
        }
      }
    @endphp
    <!-- Navbar-->
    <header class="app-header">
      <a class="app-header__logo" href="{{ $dashboardUrl }}">MEDALLION</a>
      <!-- Sidebar toggle button-->
      <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
      <!-- Navbar Right Menu-->
      <ul class="app-nav">
        <li class="app-search" style="position: relative;">
          <input class="app-search__input" type="search" placeholder="Global Search..." id="global-search-input">
          <button class="app-search__button"><i class="fa fa-search"></i></button>
          <!-- Search Results Dropdown -->
          <div id="search-results-dropdown" style="display:none; position:absolute; top:100%; right:0; min-width:320px; background:#fff; box-shadow:0 10px 40px rgba(0,0,0,0.2); border-radius:8px; z-index:9999; max-height:450px; overflow-y:auto; border: 1px solid #eee;">
            <div id="search-results-content" style="padding: 10px;"></div>
          </div>
        </li>
        @php
            $isOwner = Auth::check() && !session('is_staff');
            $isManager = false;
            $ownerId = null;

            if ($isOwner) {
                $ownerId = Auth::id();
            } elseif (session('is_staff')) {
                $ownerId = session('staff_user_id');
                $staff = \App\Models\Staff::with('role')->find(session('staff_id'));
                if ($staff && $staff->role) {
                    $roleName = strtolower(trim($staff->role->name ?? ''));
                    $roleSlug = strtolower(trim($staff->role->slug ?? ''));
                    if ($roleName === 'manager' || $roleSlug === 'manager' || $roleSlug === 'super-admin' || $roleName === 'super admin' || $staff->role->hasPermission('branch_management', 'view')) {
                        $isManager = true;
                    }
                }
            }
        @endphp

        @if($isOwner || $isManager)
        @php
            $staffLocations = \App\Models\Staff::where('user_id', $ownerId)->whereNotNull('location_branch')->pluck('location_branch')->unique();
            $tableLocations = \App\Models\BarTable::where('user_id', $ownerId)->whereNotNull('location')->pluck('location')->unique();
            $userLocations = $staffLocations->merge($tableLocations)->unique()->filter()->sort();
            $activeLocation = session('active_location', 'all');
        @endphp
        
        @if($userLocations->count() > 1)
        <!-- Branch Context Switcher -->
        <li class="dropdown">
          <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Switch Branch" style="background-color: rgba(255,255,255,0.1); border-radius: 4px; padding: 5px 15px; margin: 8px 10px; font-size: 13px;">
            <i class="fa fa-map-marker mr-2"></i> 
            <span class="d-none d-md-inline">{{ $activeLocation == 'all' ? 'All Branches' : $activeLocation }}</span>
            <i class="fa fa-caret-down ml-2"></i>
          </a>
          <ul class="dropdown-menu settings-menu dropdown-menu-right">
            <li>
              <a class="dropdown-item {{ $activeLocation == 'all' ? 'active' : '' }}" href="javascript:void(0)" onclick="switchLocation('all')">
                <i class="fa fa-globe fa-lg"></i> All Branches
              </a>
            </li>
            @foreach($userLocations as $location)
            <li>
              <a class="dropdown-item {{ $activeLocation == $location ? 'active' : '' }}" href="javascript:void(0)" onclick="switchLocation('{{ $location }}')">
                <i class="fa fa-map-marker fa-lg"></i> {{ $location }}
              </a>
            </li>
            @endforeach
          </ul>
        </li>
        <form id="location-switch-form" action="{{ route('location.switch') }}" method="POST" style="display: none;">
          @csrf
          <input type="hidden" name="active_location" id="location-switch-input">
        </form>
        @endif
        @endif
        <!--Notification Menu-->
        <li class="dropdown" id="notification-dropdown">
          <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Show notifications">
            <i class="fa fa-bell-o fa-lg"></i>
            <span class="badge badge-danger" id="notification-badge" style="position:absolute; top:10px; right:10px; font-size:10px; padding: 2px 4px; display:none;">0</span>
          </a>
          <ul class="app-notification dropdown-menu dropdown-menu-right">
            <li class="app-notification__title d-flex justify-content-between align-items-center">
              <span id="notification-count-text">Loading notifications...</span>
              <a href="javascript:void(0)" id="btn-clear-notifications" class="small text-primary" style="display:none; text-decoration:none;">Mark all as read</a>
            </li>
            <div class="app-notification__content" id="notification-list" style="max-height: 350px; overflow-y: auto;">
              <!-- Dynamic notifications will load here -->
              <div class="text-center p-3 text-muted" id="notification-empty" style="display:none;">
                <i class="fa fa-bell-slash-o fa-2x mb-2 d-block"></i>
                No new notifications
              </div>
            </div>
            <li class="app-notification__footer"><a href="#">See all notifications.</a></li>
          </ul>
        </li>
        <!-- User Menu-->
        <li class="dropdown">
          <a class="app-nav__item" href="#" data-toggle="dropdown" aria-label="Open Profile Menu">
            @php
              $isStaff = session('is_staff');
              $profileImg = null;
              if ($isStaff) {
                $staffObj = \App\Models\Staff::find(session('staff_id'));
                $profileImg = $staffObj ? $staffObj->profile_image : null;
              } else {
                $profileImg = auth()->user()->profile_image ?? null;
              }
            @endphp
            @if($profileImg)
              <img src="{{ asset('storage/' . $profileImg) }}" class="rounded-circle" style="width: 25px; height: 25px; object-fit: cover; margin-top: -5px;">
            @else
              <i class="fa fa-user fa-lg"></i>
            @endif
          </a>
          <ul class="dropdown-menu settings-menu dropdown-menu-right">
            <li><a class="dropdown-item" href="{{ route('profile.index') }}"><i class="fa fa-user fa-lg"></i> Profile</a></li>
            <li>
              <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="dropdown-item" style="border: none; background: none; width: 100%; text-align: left; padding: 0.5rem 1.5rem;">
                  <i class="fa fa-sign-out fa-lg"></i> Logout
                </button>
              </form>
            </li>
          </ul>
        </li>
      </ul>
    </header>
    <!-- Sidebar menu-->
    <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
    <aside class="app-sidebar">
      <div class="app-sidebar__user">
        @php
          $isStaff = session('is_staff');
          $sidebarProfileImg = null;
          if ($isStaff) {
            $staffObj = \App\Models\Staff::find(session('staff_id'));
            $sidebarProfileImg = $staffObj ? $staffObj->profile_image : null;
          } else {
            $sidebarProfileImg = auth()->user()->profile_image ?? null;
          }
        @endphp

        @if($isStaff)
          @if($sidebarProfileImg)
            <img class="app-sidebar__user-avatar" src="{{ asset('storage/' . $sidebarProfileImg) }}?v={{ time() }}" alt="Staff Image" style="width: 48px; height: 48px; object-fit: cover;">
          @else
            <img class="app-sidebar__user-avatar" src="https://ui-avatars.com/api/?name={{ urlencode(session('staff_name')) }}&background=940000&color=fff" alt="Staff Image">
          @endif
          <div>
            <p class="app-sidebar__user-name">{{ session('staff_name') }}</p>
            <p class="app-sidebar__user-designation">
              @php
                $staffRole = \App\Models\Role::find(session('staff_role_id'));
              @endphp
              {{ $staffRole ? $staffRole->name : 'Staff' }}
            </p>
          </div>
        @elseif(Auth::check())
          @if($sidebarProfileImg)
            <img class="app-sidebar__user-avatar" src="{{ asset('storage/' . $sidebarProfileImg) }}?v={{ time() }}" alt="User Image" style="width: 48px; height: 48px; object-fit: cover;">
          @else
            <img class="app-sidebar__user-avatar" src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=940000&color=fff" alt="User Image">
          @endif
          <div>
            <p class="app-sidebar__user-name">{{ Auth::user()->name }}</p>
            <p class="app-sidebar__user-designation">
              @php
                $userRoles = Auth::user()->userRoles()->get();
              @endphp
              @if($userRoles->count() > 0)
                {{ $userRoles->first()->name }}
              @else
                {{ Auth::user()->email }}
              @endif
            </p>
          </div>
        @endif
      </div>
      <ul class="app-menu">
        @if(session('is_staff'))
          {{-- Staff Menu - Show only what their role allows --}}
          @php
            $staffRole = \App\Models\Role::with('permissions')->find(session('staff_role_id'));
            $owner = \App\Models\User::find(session('staff_user_id'));
            $menuService = new \App\Services\MenuService();
            $staffMenus = $menuService->getStaffMenus($staffRole, $owner);
            
            // Calculate pending stock transfers for badge notification
            $pendingTransfersCount = 0;
            if ($owner && $staff && $staff->role && ($staff->role->hasPermission('inventory', 'view') || in_array(strtolower($staff->role->name ?? ''), ['stock keeper', 'manager']))) {
              $pendingTransfersCount = \App\Models\StockTransfer::where('user_id', $owner->id)
                ->where('status', 'pending')
                ->count();
            }
          @endphp
          @if($staffMenus && $staffMenus->count() > 0)
            @php
              $commonMenuSlugs = ['dashboard', 'sales', 'products', 'customers', 'staff', 'reports', 'settings'];
              $currentBusinessType = null;
              $hasShownCommonMenus = false;
              $hasShownGeneralHeader = false;
              $processedMenuIds = [];
            @endphp
            @foreach($staffMenus as $menu)
              @php
                // Skip if already processed
                if (in_array($menu->id, $processedMenuIds)) {
                  continue;
                }
                $processedMenuIds[] = $menu->id;
                
                // Format icon
                $menuIcon = $menu->icon ?? 'fa-circle';
                if (strpos($menuIcon, 'fa ') === false) {
                  $menuIcon = 'fa ' . (strpos($menuIcon, 'fa-') === 0 ? $menuIcon : 'fa-' . $menuIcon);
                }
                
                // Generate full URL
                $menu->full_url = isset($menu->route) && $menu->route ? route($menu->route) : '#';
                
                // Check if this is a common menu or business-specific menu
                $isCommonMenu = in_array($menu->slug, $commonMenuSlugs);
                $isBusinessSpecific = isset($menu->business_type_id) && !$isCommonMenu;
                $isPlaceholder = isset($menu->is_placeholder) && $menu->is_placeholder;

                // Show General Header at the very beginning
                $showGeneralHeader = false;
                if (!$hasShownGeneralHeader && $isCommonMenu) {
                  $showGeneralHeader = true;
                  $hasShownGeneralHeader = true;
                }
                
                // Track if we've shown common menus
                if ($isCommonMenu) {
                  $hasShownCommonMenus = true;
                }
                
                // Show business type separator if this is a new business type
                $showSeparator = false;
                
                if ($isBusinessSpecific && isset($menu->business_type_id)) {
                  if ($currentBusinessType !== $menu->business_type_id) {
                    $currentBusinessType = $menu->business_type_id;
                    $showSeparator = true;
                  }
                }
              @endphp

              {{-- Main Navigation Header (Removed) --}}
              
              {{-- Business Modules Header (Removed) --}}

              {{-- Business Type Separator (Removed) --}}
              
              @if($menu->children && $menu->children->count() > 0)
                <li class="treeview {{ request()->routeIs($menu->route ?? '') || ($menu->children && $menu->children->contains(function($child) { return request()->routeIs($child->route ?? ''); })) ? 'is-expanded' : '' }}">
                  <a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview">
                    <i class="app-menu__icon {{ $menuIcon }}"></i>
                    <span class="app-menu__label">{{ $menu->name }}</span>
                    @if(strtolower($menu->name ?? '') === 'stock transfers' && $pendingTransfersCount > 0)
                      <span class="badge badge-danger" style="margin-left: 8px;">{{ $pendingTransfersCount }}</span>
                    @endif
                    <i class="treeview-indicator fa fa-angle-right"></i>
                  </a>
                  <ul class="treeview-menu">
                    @foreach($menu->children as $child)
                      @php
                        $childIcon = $child->icon ?? 'fa-circle-o';
                        if (strpos($childIcon, 'fa ') === false) {
                          $childIcon = 'fa ' . (strpos($childIcon, 'fa-') === 0 ? $childIcon : 'fa-' . $childIcon);
                        }
                        $child->full_url = isset($child->route) && $child->route ? route($child->route) : '#';
                      @endphp
                      <li>
                        <a class="treeview-item {{ request()->routeIs($child->route ?? '') ? 'active' : '' }}" href="{{ $child->full_url }}">
                          <i class="icon {{ $childIcon }}"></i> {{ $child->name }}
                          @if(strtolower($child->name ?? '') === 'all transfers' && $pendingTransfersCount > 0)
                            <span class="badge badge-danger" style="margin-left: 8px;">{{ $pendingTransfersCount }}</span>
                          @endif
                        </a>
                      </li>
                    @endforeach
                  </ul>
                </li>
              @else
                <li>
                  <a class="app-menu__item {{ request()->routeIs($menu->route ?? '') ? 'active' : '' }}" href="{{ $menu->full_url }}">
                    <i class="app-menu__icon {{ $menuIcon }}"></i>
                    <span class="app-menu__label">{{ $menu->name }}</span>
                  </a>
                </li>
              @endif
              <li style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin: 2px 15px;"></li>
            @endforeach
          @else
            {{-- Fallback: Show only Dashboard if no menus available --}}
            <li>
              <a class="app-menu__item {{ request()->routeIs('dashboard*') ? 'active' : '' }}" href="{{ $dashboardUrl }}">
                <i class="app-menu__icon fa fa-dashboard"></i>
                <span class="app-menu__label">Dashboard</span>
              </a>
            </li>
          @endif
        @elseif(Auth::check())
        {{-- Show only Dashboard menu during configuration --}}
        @if(request()->routeIs('business-configuration.*'))
          <li>
            <a class="app-menu__item {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
              <i class="app-menu__icon fa fa-dashboard"></i>
              <span class="app-menu__label">Dashboard</span>
            </a>
          </li>
        @else
          {{-- Show full dynamic menu after configuration --}}
          @php
            $menuService = new \App\Services\MenuService();
            $menus = $menuService->getUserMenus(Auth::user());
          @endphp
          
          @if($menus && $menus->count() > 0)
            @php
              $commonMenuSlugs = ['dashboard', 'sales', 'products', 'customers', 'staff', 'reports', 'settings'];
              $currentBusinessType = null;
              $hasShownCommonMenus = false;
              $hasShownGeneralHeader = false;
              $businessTypeNames = ['Bar', 'Restaurant', 'Pharmacy', 'Retail Store', 'Supermarket', 'Hotel', 'Cafe', 'Bakery', 'Clothing Store', 'Electronics Store', 'General Store'];
              $businessTypeSlugs = ['bar', 'restaurant', 'pharmacy', 'retail-store', 'supermarket', 'hotel', 'cafe', 'bakery', 'clothing-store', 'electronics-store', 'general-store'];
              $processedMenuIds = [];
            @endphp
            @foreach($menus as $menu)
              @php
                // Skip duplicates by ID
                if (in_array($menu->id, $processedMenuIds)) {
                  continue;
                }
                $processedMenuIds[] = $menu->id;
                
                // Skip menu items that are just business type names or slugs
                if (in_array($menu->name, $businessTypeNames) || in_array($menu->slug ?? '', $businessTypeSlugs)) {
                  continue;
                }
                
                $children = isset($menu->children) ? $menu->children : collect();
                $hasChildren = $children && $children->count() > 0;
                $isCommonMenu = in_array($menu->slug, $commonMenuSlugs);
                $isBusinessSpecific = isset($menu->business_type_id) && !$isCommonMenu;
                $isPlaceholder = isset($menu->is_placeholder) && $menu->is_placeholder;
                
                // Show General Header
                $showGeneralHeader = false;
                if (!$hasShownGeneralHeader && $isCommonMenu) {
                  $showGeneralHeader = true;
                  $hasShownGeneralHeader = true;
                }

                // Track if we've shown common menus
                if ($isCommonMenu) {
                  $hasShownCommonMenus = true;
                }
                
                // Show business type separator if this is a new business type
                $showSeparator = false;
                if (($isBusinessSpecific || $isPlaceholder) && isset($menu->business_type_id)) {
                  if ($currentBusinessType !== $menu->business_type_id) {
                    $currentBusinessType = $menu->business_type_id;
                    $showSeparator = true;
                  }
                }
                
                // Format icon - add 'fa' prefix if not present
                $menuIcon = $menu->icon ?? 'fa-circle';
                if (strpos($menuIcon, 'fa ') === false) {
                  $menuIcon = 'fa ' . (strpos($menuIcon, 'fa-') === 0 ? $menuIcon : 'fa-' . $menuIcon);
                }
              @endphp
              
              {{-- Main Navigation Header --}}
              {{-- Main Navigation Header (Removed) --}}

              {{-- Business Modules Header (Removed) --}}

              {{-- Business Type Separator (Removed) --}}
              
              {{-- Render placeholder: styled header for Super Admin section, silent skip for business type separators --}}
              @if($isPlaceholder)
                @if(isset($menu->slug) && $menu->slug === 'super-admin-controls-sep')
                  <li style="margin: 12px 15px 4px; padding: 6px 10px; border-top: 1px solid rgba(255,255,255,0.15); font-size: 10px; font-weight: 700; letter-spacing: 1.5px; color: rgba(255,255,255,0.5); text-transform: uppercase;">
                    <i class="fa {{ $menu->icon ?? 'fa-shield' }}" style="margin-right:5px;"></i> {{ $menu->name }}
                  </li>
                @endif
                {{-- Other placeholders are silent business-type separators --}}

              @elseif($hasChildren)
                <li class="treeview {{ request()->routeIs($menu->route ?? '') || ($menu->children && $menu->children->contains(function($child) { return request()->routeIs($child->route ?? ''); })) ? 'is-expanded' : '' }}">
                  <a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview">
                    <i class="app-menu__icon {{ $menuIcon }}"></i>
                    <span class="app-menu__label">{{ $menu->name }}</span>
                    <i class="treeview-indicator fa fa-angle-right"></i>
                  </a>
                  <ul class="treeview-menu">
                    @foreach($children as $child)
                      @php
                        $childIcon = $child->icon ?? 'fa-circle-o';
                        if (strpos($childIcon, 'fa ') === false) {
                          $childIcon = 'fa ' . (strpos($childIcon, 'fa-') === 0 ? $childIcon : 'fa-' . $childIcon);
                        }
                        // Generate full URL for child menu
                        $childFullUrl = isset($child->route) && $child->route ? route($child->route) : '#';
                        // Check if child has its own children (nested menus)
                        $childHasChildren = isset($child->children) && $child->children && $child->children->count() > 0;
                      @endphp
                      @if($childHasChildren)
                        <li class="treeview {{ request()->routeIs($child->route ?? '') || ($child->children && $child->children->contains(function($grandchild) { return request()->routeIs($grandchild->route ?? ''); })) ? 'is-expanded' : '' }}">
                          <a class="treeview-item" href="javascript:void(0);" data-toggle="treeview">
                            <i class="icon {{ $childIcon }}"></i> {{ $child->name }}
                            <i class="treeview-indicator fa fa-angle-right"></i>
                          </a>
                          <ul class="treeview-menu">
                            @foreach($child->children as $grandchild)
                              @php
                                $grandchildIcon = $grandchild->icon ?? 'fa-circle-o';
                                if (strpos($grandchildIcon, 'fa ') === false) {
                                  $grandchildIcon = 'fa ' . (strpos($grandchildIcon, 'fa-') === 0 ? $grandchildIcon : 'fa-' . $grandchildIcon);
                                }
                                $grandchildFullUrl = isset($grandchild->route) && $grandchild->route ? route($grandchild->route) : '#';
                              @endphp
                              <li>
                                <a class="treeview-item {{ request()->routeIs($grandchild->route ?? '') ? 'active' : '' }}" href="{{ $grandchildFullUrl }}">
                                  <i class="icon {{ $grandchildIcon }}"></i> {{ $grandchild->name }}
                                </a>
                              </li>
                            @endforeach
                          </ul>
                        </li>
                      @else
                        <li>
                          <a class="treeview-item {{ request()->routeIs($child->route ?? '') ? 'active' : '' }}" href="{{ $childFullUrl }}">
                            <i class="icon {{ $childIcon }}"></i> {{ $child->name }}
                            @if(strtolower($child->name ?? '') === 'all transfers' && $pendingTransfersCount > 0)
                              <span class="badge badge-danger" style="margin-left: 8px;">{{ $pendingTransfersCount }}</span>
                            @endif
                          </a>
                        </li>
                      @endif
                    @endforeach
                  </ul>
                </li>
              @else
                <li>
                  <a class="app-menu__item {{ request()->routeIs($menu->route ?? '') ? 'active' : '' }}" href="{{ $menu->full_url }}">
                    <i class="app-menu__icon {{ $menuIcon }}"></i>
                    <span class="app-menu__label">{{ $menu->name }}</span>
                  </a>
                </li>
              @endif
              <li style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin: 2px 15px;"></li>
            @endforeach
          @else
            {{-- Fallback to default menu if no configuration --}}
            <li>
              <a class="app-menu__item {{ request()->routeIs('dashboard*') ? 'active' : '' }}" href="{{ $dashboardUrl }}">
                <i class="app-menu__icon fa fa-dashboard"></i>
                <span class="app-menu__label">Dashboard</span>
              </a>
            </li>
          @endif
        @endif
        @endif
        {{-- Bar Stock Sheet: show for staff who have inventory access or counter/keeper role --}}
        @if(session('is_staff'))
          @php
            $sidebarStaff = \App\Models\Staff::with('role')->find(session('staff_id'));
            $sidebarRole  = strtolower(trim($sidebarStaff?->role?->name ?? ''));
            $showStockSheet = in_array($sidebarRole, ['counter', 'bar counter', 'stock keeper', 'stockkeeper', 'manager', 'barkeeper', 'bar keeper', 'accountant']);
          @endphp
          @if($showStockSheet)
          <li style="border-bottom: 1px solid rgba(255,255,255,0.1); margin: 2px 15px;"></li>
          
          @if(in_array($sidebarRole, ['counter', 'bar counter']))
            {{-- Simplified Counter View --}}
            <li>
              <a class="app-menu__item {{ request()->routeIs('bar.counter.waiter-orders') ? 'active' : '' }}" href="{{ route('bar.counter.waiter-orders') }}">
                <i class="app-menu__icon fa fa-shopping-basket"></i>
                <span class="app-menu__label">My Orders</span>
              </a>
            </li>
            <li style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin: 2px 15px;"></li>

            <li>
              <a class="app-menu__item {{ request()->routeIs('bar.stock-transfers.*') ? 'active' : '' }}" href="{{ route('bar.stock-transfers.index') }}">
                <i class="app-menu__icon fa fa-exchange"></i>
                <span class="app-menu__label">Stock Transfers</span>
              </a>
            </li>
            <li style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin: 2px 15px;"></li>
            <li>
              <a class="app-menu__item {{ request()->routeIs('bar.counter.reconciliation') ? 'active' : '' }}" href="{{ route('bar.counter.reconciliation', ['date' => now()->toDateString()]) }}">
                <i class="app-menu__icon fa fa-times-circle"></i>
                <span class="app-menu__label">Close Shift</span>
              </a>
            </li>
            <li style="border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin: 2px 15px;"></li>
            <li>
              <a class="app-menu__item {{ request()->routeIs('profile.index') ? 'active' : '' }}" href="{{ route('profile.index') }}">
                <i class="app-menu__icon fa fa-user-circle"></i>
                <span class="app-menu__label">Profile</span>
              </a>
            </li>
          @else
            {{-- Standard Stock Sheet View --}}
            <li class="treeview {{ request()->routeIs('bar.stock-sheet') ? 'is-expanded' : '' }}">
              <a class="app-menu__item" href="#" data-toggle="treeview">
                <i class="app-menu__icon fa fa-clipboard"></i>
                <span class="app-menu__label">Stock Sheet</span>
                <i class="treeview-indicator fa fa-angle-right"></i>
              </a>
              <ul class="treeview-menu">
                <li>
                  <a class="treeview-item {{ request()->routeIs('bar.stock-sheet') && (request()->route('location') == 'warehouse' || !request()->route('location')) ? 'active' : '' }}" 
                     href="{{ route('bar.stock-sheet', 'warehouse') }}">
                    <i class="icon fa fa-angle-right"></i> Warehouse
                  </a>
                </li>
                <li>
                  <a class="treeview-item {{ request()->routeIs('bar.stock-sheet') && request()->route('location') == 'counter' ? 'active' : '' }}" 
                     href="{{ route('bar.stock-sheet', 'counter') }}">
                    <i class="icon fa fa-angle-right"></i> Counter
                  </a>
                </li>
              </ul>
            </li>
          @endif
          @endif
        @endif
        {{-- QR & Feedback Portal --}}
        @php
            $isOwnerForSidebar = Auth::check() && !session('is_staff');
            $isManagerForSidebar = false;
            if (session('is_staff')) {
                $sidebarStaffObj = \App\Models\Staff::with('role')->find(session('staff_id'));
                if ($sidebarStaffObj && $sidebarStaffObj->role) {
                    $sidebarRoleName = strtolower(trim($sidebarStaffObj->role->name ?? ''));
                    if (in_array($sidebarRoleName, ['manager', 'admin', 'super admin']) || $sidebarStaffObj->role->slug === 'super-admin') {
                        $isManagerForSidebar = true;
                    }
                }
            }
        @endphp

        @if($isOwnerForSidebar || $isManagerForSidebar)
          <li style="border-bottom: 1px solid rgba(255,255,255,0.1); margin: 2px 15px;"></li>
          <li>
            <a class="app-menu__item {{ request()->routeIs('manager.live-sales') ? 'active' : '' }}" href="{{ route('manager.live-sales') }}">
              <i class="app-menu__icon fa fa-bolt"></i>
              <span class="app-menu__label">Live Sales Pulse</span>
            </a>
          </li>
          <li>
            <a class="app-menu__item {{ request()->routeIs('manager.attendance.index') ? 'active' : '' }}" href="{{ route('manager.attendance.index') }}">
              <i class="app-menu__icon fa fa-clock-o"></i>
              <span class="app-menu__label">Attendance Log</span>
            </a>
          </li>
          <li style="border-bottom: 1px solid rgba(255,255,255,0.1); margin: 2px 15px;"></li>
          <li class="treeview {{ request()->routeIs('manager.qr-codes.index') || request()->routeIs('manager.feedback.index') ? 'is-expanded' : '' }}">
            <a class="app-menu__item" href="#" data-toggle="treeview">
              <i class="app-menu__icon fa fa-qrcode"></i>
              <span class="app-menu__label">Customer Portals</span>
              <i class="treeview-indicator fa fa-angle-right"></i>
            </a>
            <ul class="treeview-menu">
              <li>
                <a class="treeview-item {{ request()->routeIs('manager.qr-codes.index') ? 'active' : '' }}" href="{{ route('manager.qr-codes.index') }}">
                  <i class="icon fa fa-qrcode"></i> QR Management
                </a>
              </li>
              <li>
                <a class="treeview-item {{ request()->routeIs('manager.feedback.index') ? 'active' : '' }}" href="{{ route('manager.feedback.index') }}">
                  <i class="icon fa fa-comments"></i> Customer Feedback
                </a>
              </li>
            </ul>
          </li>
        @endif

        {{-- Only show Payments & Invoices if NOT on configuration page and NOT staff --}}
        @if(Auth::check() && !Auth::user()->isAdmin() && !request()->routeIs('business-configuration.*') && !session('is_staff'))
        <li>
          <a class="app-menu__item {{ request()->routeIs('payments.*') || request()->routeIs('invoices.*') ? 'active' : '' }}" href="{{ route('payments.history') }}">
            <i class="app-menu__icon fa fa-credit-card"></i>
            <span class="app-menu__label">Payments & Invoices</span>
          </a>
        </li>
        @endif
        {{-- SaaS Platform Menus removed for Super Admin - now managed via Admin Panel only --}}

        
        {{-- Food & Kitchen Management --}}
        @php
          $sidebarStaff = session('is_staff') ? \App\Models\Staff::with('role')->find(session('staff_id')) : null;
          $sidebarRole  = $sidebarStaff ? strtolower(trim($sidebarStaff->role->name ?? '')) : '';
          $isAccountant = in_array($sidebarRole, ['accountant', 'manager', 'admin', 'finance', 'account', 'super admin']) || ($sidebarStaff && $sidebarStaff->role && $sidebarStaff->role->slug === 'super-admin');
          $isWaiter = ($sidebarRole === 'waiter');
          $isOwner = (Auth::check() && !Auth::user()->isAdmin() && !session('is_staff'));
        @endphp

        @if($isWaiter)
        <li style="border-bottom: 1px solid rgba(255,255,255,0.1); margin: 2px 15px;"></li>
        <li>
          <a class="app-menu__item {{ request()->routeIs('bar.waiter.food-sales') ? 'active' : '' }}" href="{{ route('bar.waiter.food-sales') }}">
            <i class="app-menu__icon fa fa-cutlery"></i>
            <span class="app-menu__label">My Food Sales</span>
          </a>
        </li>
        @endif

        {{-- Settings is now included in dynamic menus, no need for hardcoded version --}}
      </ul>
    </aside>
    <main class="app-content">
      @yield('content')
    </main>
    <!-- Essential javascripts for application to work-->
    <script src="{{ asset('js/admin/jquery-3.2.1.min.js') }}"></script>
    <script src="{{ asset('js/admin/popper.min.js') }}"></script>
    <script src="{{ asset('js/admin/bootstrap.min.js') }}"></script>
    <script src="{{ asset('js/admin/main.js') }}"></script>
    <!-- The javascript plugin to display page loading on top-->
    <script src="{{ asset('js/admin/plugins/pace.min.js') }}"></script>
    <script>
      // Prevent FOUC - Show content when page is ready
      (function() {
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
          });
        } else {
          document.body.classList.add('loaded');
        }
      })();
    </script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
      // ============================================================================
      // DUAL NOTIFICATION SYSTEM
      // ============================================================================
      
      // 1. TOAST NOTIFICATIONS (Non-intrusive, auto-dismiss)
      // ────────────────────────────────────────────────────────────────────────────
      const Toast = typeof Swal !== 'undefined' ? Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
          toast.addEventListener('mouseenter', Swal.stopTimer);
          toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
      }) : null;

      /**
       * Show a non-intrusive toast notification with fallback
       */
      function showToast(type, message, title = null, duration = 3000) {
        if (typeof Swal !== 'undefined' && Toast) {
          const toastConfig = {
            icon: type,
            title: title || message,
            timer: duration
          };
          if (title && message && title !== message) {
            toastConfig.title = title;
            toastConfig.text = message;
          }
          Toast.fire(toastConfig);
        } else {
          // Fallback to standard alert if Swal fails
          console.warn('SweetAlert2 not loaded. Falling back to standard alert.');
          alert((title ? title + ': ' : '') + message);
        }
      }

      // 2. SWEETALERT MODAL POPUPS (Attention-grabbing, requires user action)
      // ────────────────────────────────────────────────────────────────────────────
      
      /**
       * Show a SweetAlert modal dialog
       * @param {string} type - 'success', 'error', 'warning', 'info', 'question'
       * @param {string} message - The alert message
       * @param {string|null} title - Optional title
       * @param {object} options - Additional SweetAlert options
       */
      function showAlert(type, message, title = null, options = {}) {
        if (typeof Swal !== 'undefined') {
          const defaultOptions = {
            icon: type,
            title: title || (type.charAt(0).toUpperCase() + type.slice(1)),
            text: message,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d'
          };
          Swal.fire({...defaultOptions, ...options});
        } else {
          alert((title ? title + ': ' : '') + message);
        }
      }

      /**
       * Show a confirmation dialog with Yes/No buttons
       * @param {string} message - The confirmation message
       * @param {string} title - The dialog title
       * @param {function} onConfirm - Callback function when confirmed
       * @param {function} onCancel - Optional callback when cancelled
       */
      function showConfirm(message, title = 'Are you sure?', onConfirm, onCancel = null) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#940000',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes',
            cancelButtonText: 'No'
          }).then((result) => {
            if (result.isConfirmed && onConfirm) {
              onConfirm();
            } else if (result.isDismissed && onCancel) {
              onCancel();
            }
          });
        } else {
          if (confirm(title + "\n\n" + message)) {
            if (onConfirm) onConfirm();
          } else {
            if (onCancel) onCancel();
          }
        }
      }

      // 3. SESSION MESSAGE INTEGRATION
      // ────────────────────────────────────────────────────────────────────────────
      // By default, session messages use TOAST notifications (non-intrusive)
      // To use modal alerts instead, change showToast() to showAlert()
      
      @if(session('success'))
        showToast('success', '{{ session('success') }}', 'Success!');
      @endif
      
      @if(session('error'))
        showToast('error', '{{ session('error') }}', 'Error!');
      @endif
      
      @if(session('warning'))
        showToast('warning', '{{ session('warning') }}', 'Warning!');
      @endif
      
      @if(session('info'))
        showToast('info', '{{ session('info') }}', 'Info');
      @endif

      // For critical session messages that need modal alerts, use 'alert_success', 'alert_error', etc.
      @if(session('alert_success'))
        showAlert('success', '{{ session('alert_success') }}', 'Success!');
      @endif
      
      @if(session('alert_error'))
        showAlert('error', '{{ session('alert_error') }}', 'Error!');
      @endif
      
      @if(session('alert_warning'))
        showAlert('warning', '{{ session('alert_warning') }}', 'Warning!');
      @endif
      
      @if(session('alert_info'))
        showAlert('info', '{{ session('alert_info') }}', 'Info');
      @endif

      @if($errors->any())
        @foreach($errors->all() as $error)
          showToast('error', '{!! addslashes($error) !!}', 'Validation Error');
        @endforeach
      @endif

      function switchLocation(location) {
        document.getElementById('location-switch-input').value = location;
        document.getElementById('location-switch-form').submit();
      }

      // Real-time Staff Search
      const searchInput = document.getElementById('staffSearch');
      if (searchInput) {
        searchInput.addEventListener('keyup', function() {
          const value = this.value.toLowerCase();
          const rows = document.querySelectorAll('tbody tr');
          
          rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(value) ? '' : 'none';
          });
        });
      }

      // GLOBAL SEARCH LOGIC
      const gSearchInput = document.getElementById('global-search-input');
      const gSearchDropdown = document.getElementById('search-results-dropdown');
      const gSearchContent = document.getElementById('search-results-content');
      let searchTimeout = null;

      if (gSearchInput) {
          gSearchInput.addEventListener('input', function() {
              clearTimeout(searchTimeout);
              const q = this.value.trim();
              
              if (q.length < 2) {
                  gSearchDropdown.style.display = 'none';
                  return;
              }

              searchTimeout = setTimeout(() => {
                  fetch(`/api/search?q=${encodeURIComponent(q)}`)
                      .then(res => res.json())
                      .then(data => {
                          if (data.results.length > 0) {
                              let html = '<div style="font-size:11px; color:#888; margin-bottom:10px; font-weight:600; padding:0 5px;">SEARCH RESULTS</div>';
                              data.results.forEach(res => {
                                  html += `
                                      <a href="${res.link}" class="d-flex align-items-center mb-2 p-2 rounded search-item" style="text-decoration:none; color:inherit; border-bottom:1px solid #f8f9fa;">
                                          <div style="width:32px; height:32px; background:#f0f0f0; border-radius:4px; display:flex; align-items:center; justify-content:center; margin-right:12px;">
                                              <i class="fa ${res.icon} text-secondary"></i>
                                          </div>
                                          <div style="flex:1; overflow:hidden;">
                                              <div style="font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${res.label}</div>
                                              <div style="font-size:11px; color:#777;">${res.type} · ${res.sub}</div>
                                          </div>
                                      </a>
                                  `;
                              });
                              gSearchContent.innerHTML = html;
                              gSearchDropdown.style.display = 'block';
                          } else {
                              gSearchContent.innerHTML = '<div class="text-center p-4 text-muted">No results found for "' + q + '"</div>';
                              gSearchDropdown.style.display = 'block';
                          }
                      });
              }, 300);
          });

          // Close search dropdown on click outside
          document.addEventListener('click', function(e) {
              if (!gSearchInput.contains(e.target) && !gSearchDropdown.contains(e.target)) {
                  gSearchDropdown.style.display = 'none';
              }
          });
      }

      // GLOBAL NOTIFICATIONS LOGIC
      function updateNotifications() {
          fetch('/api/notifications')
              .then(res => res.json())
              .then(data => {
                  const badge = document.getElementById('notification-badge');
                  const countText = document.getElementById('notification-count-text');
                  const list = document.getElementById('notification-list');
                  const empty = document.getElementById('notification-empty');
                  const clearLink = document.getElementById('btn-clear-notifications');
                  
                  // Safety check: Ensure all required elements exist
                  if (!badge || !countText || !list || !empty) return;

                  if (data.count > 0) {
                      badge.innerText = data.count;
                      badge.style.display = 'inline-block';
                      countText.innerText = `You have ${data.count} new notifications`;
                      empty.style.display = 'none';
                      if (clearLink) clearLink.style.display = 'inline';
                      
                      let html = '';
                      data.notifications.forEach(n => {
                          html += `
                              <li>
                                  <a class="app-notification__item" href="${n.link}">
                                      <span class="app-notification__icon">
                                          <span class="fa-stack fa-lg">
                                              <i class="fa fa-circle fa-stack-2x ${n.color}"></i>
                                              <i class="fa ${n.icon} fa-stack-1x fa-inverse"></i>
                                          </span>
                                      </span>
                                      <div>
                                          <p class="app-notification__message" style="font-size:13px; margin-bottom:2px;">${n.message}</p>
                                          <p class="app-notification__meta" style="font-size:11px;">${n.time}</p>
                                      </div>
                                  </a>
                              </li>
                          `;
                      });
                      list.innerHTML = html;
                  } else {
                      badge.style.display = 'none';
                      countText.innerText = 'No new notifications';
                      list.innerHTML = '';
                      empty.style.display = 'block';
                      list.appendChild(empty);
                      if (clearLink) clearLink.style.display = 'none';
                  }
              })
              .catch(err => console.debug('Notifications sync paused...'));
      }

      // Handle clear all
      document.getElementById('btn-clear-notifications')?.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          fetch('/api/notifications/clear', {
              method: 'POST',
              headers: {
                  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                  'Accept': 'application/json'
              }
          }).then(() => updateNotifications());
      });

      // Initial load and periodic update
      updateNotifications();
      setInterval(updateNotifications, 120000); // Update every 2 mins

    </script>
    @yield('scripts')
    @stack('scripts')
  </body>
</html>
