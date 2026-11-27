<div class="main-sidebar">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="{{ route('admin.dashboard') }}"><img class="admin_logo" src="{{ asset($setting->logo) ?? '' }}"
                    alt="{{ $setting->app_name ?? '' }}"></a>
        </div>

        <div class="sidebar-brand sidebar-brand-sm">
            <a href="{{ route('admin.dashboard') }}"><img src="{{ asset($setting->favicon) ?? '' }}"
                    alt="{{ $setting->app_name ?? '' }}"></a>
        </div>

        <div class="admin-sidebar-menu-search">
            <label class="sr-only" for="admin-sidebar-menu-search">{{ __('Search menu') }}</label>
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                </div>
                <input id="admin-sidebar-menu-search" type="search" class="form-control admin-sidebar-menu-search-input"
                    placeholder="{{ __('Search menu...') }}" autocomplete="off">
            </div>
            <div class="admin-sidebar-menu-search-empty d-none" role="status">{{ __('No menu items found.') }}</div>
        </div>

        <ul class="sidebar-menu">
            @adminCan('dashboard.view')
                <li class="{{ Route::is('admin.dashboard') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('admin.dashboard') }}"><i class="fas fa-home"></i>
                        <span>{{ __('Dashboard') }}</span>
                    </a>
                </li>
            @endadminCan

            @if (Module::isEnabled('GlobalSetting') && checkAdminHasPermission('setting.view'))
                @include('globalsetting::sidebar')
            @endif
            @if (checkAdminHasPermission('basic.payment.view'))
                @if (Module::isEnabled('BasicPayment') && checkAdminHasPermission('basic.payment.view'))
                    @include('basicpayment::sidebar')
                @endif
            @endif

            @adminCan('currency.view')
                @include('currency::sidebar')
            @endadminCan

            @if (checkAdminHasPermission('admin.view') || checkAdminHasPermission('role.view'))
                <li class="menu-header">{{ __('Administration Settings') }}</li>
                <li
                    class="nav-item dropdown {{ Route::is('admin.admin.*') || Route::is('admin.role.*') ? 'active' : '' }}">
                    <a href="#" class="nav-link has-dropdown"><i
                            class="fas fa-shield-alt"></i><span>{{ __('Admin & Roles') }}</span></a>
                    <ul class="dropdown-menu">
                        @adminCan('admin.view')
                            <li class="{{ Route::is('admin.admin.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('admin.admin.index') }}">{{ __('Manage Admin') }}</a>
                            </li>
                        @endadminCan
                        @adminCan('role.view')
                            <li class="{{ Route::is('admin.role.*') ? 'active' : '' }}">
                                <a class="nav-link"
                                    href="{{ route('admin.role.index') }}">{{ __('Role & Permissions') }}</a>
                            </li>
                        @endadminCan
                    </ul>
                </li>
            @endif
        </ul>
    </aside>
</div>
