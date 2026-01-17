				<!-- main-sidebar -->
				<div class="sticky">
					<aside class="app-sidebar">
						<div class="main-sidebar-header active">
							<a class="header-logo active" href="{{url('index')}}">
								<img src="{{asset('assets/img/brand').'/'.env('APP_LOGO')}}" class="main-logo  desktop-logo" alt="logo" height="150" width="150">
								<img src="{{asset('assets/img/brand/logo-white1.png')}}" class="main-logo  desktop-dark" alt="logo" height="150" width="150">
								<img src="{{asset('assets/img/brand').'/'.env('APP_ICON')}}" class="main-logo  mobile-logo" alt="logo">
								<img src="{{asset('assets/img/brand/favicon-white1.png')}}" class="main-logo  mobile-dark" alt="logo">
							</a>
						</div>
						<div class="main-sidemenu">
							<svg width="0" height="0" style="position:absolute;">
								<defs>
									<linearGradient id="iconGradient" x1="0%" y1="0%" x2="100%" y2="100%">
										<stop offset="0%" style="stop-color:#1a1a1a;stop-opacity:1" />
										<stop offset="100%" style="stop-color:#4a4a4a;stop-opacity:1" />
									</linearGradient>
								</defs>
							</svg>
							<div class="slide-left disabled" id="slide-left"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"><path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"/></svg></div>
							<ul class="side-menu">
								<li class="side-item side-item-category">Main</li>
                                @php
                                $user = session('user');
                                $isSuperAdmin = (session('user_id') == 1) || (isset($user->role_id) && $user->role_id == 1);
                                @endphp
                                @if($user->hasPermission('view_listing'))
                                <li class="slide has-sub">
                                    <a class="side-menu__item" data-bs-toggle="collapse" href="#v2Menu" role="button" aria-expanded="false" aria-controls="v2Menu">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                        <span class="side-menu__label">V2</span>
                                        <i class="angle fe fe-chevron-down"></i>
                                    </a>
                                    <ul class="collapse ps-5" id="v2Menu">
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('listing')}}">Old Listings</a>
                                        </li>
                                        @if ($isSuperAdmin && $user->hasPermission('view_marketplace'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('v2/marketplace')}}">Marketplaces</a>
                                        </li>
                                        @endif
                                        @if ($isSuperAdmin && ($user->hasPermission('view_marketplace') || $user->hasPermission('view_listing')))
                                        <li class="slide has-sub">
                                            <a class="side-menu__item ps-0" data-bs-toggle="collapse" href="#v2OptionsMenu" role="button" aria-expanded="false" aria-controls="v2OptionsMenu">
                                                Options
                                                <i class="angle fe fe-chevron-down"></i>
                                            </a>
                                            <ul class="collapse ps-3" id="v2OptionsMenu">
                                                @if ($user->hasPermission('view_marketplace'))
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/marketplace/stock-formula')}}">Stock Formula</a>
                                                </li>
                                                @endif
                                                @if ($user->hasPermission('view_team'))
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/options/teams')}}">Teams</a>
                                                </li>
                                                @endif
                                            </ul>
                                        </li>
                                        @endif
                                        @if ($isSuperAdmin)
                                        <li class="slide has-sub">
                                            <a class="side-menu__item ps-0" data-bs-toggle="collapse" href="#v2ExtrasMenu" role="button" aria-expanded="false" aria-controls="v2ExtrasMenu">
                                                Extras
                                                <i class="angle fe fe-chevron-down"></i>
                                            </a>
                                            <ul class="collapse ps-3" id="v2ExtrasMenu">
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/stock-locks')}}">
                                                        Stock Locks
                                                    </a>
                                                </li>
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/artisan-commands')}}">
                                                        Artisan Commands
                                                    </a>
                                                </li>
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/stock-deduction-logs')}}">
                                                        Stock Deduction Logs
                                                    </a>
                                                </li>
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/listing-stock-comparisons')}}">
                                                        Stock Comparisons
                                                    </a>
                                                </li>
                                            </ul>
                                        </li>
                                        @endif
                                        @if ($isSuperAdmin)
                                        <li class="slide has-sub">
                                            <a class="side-menu__item ps-0" data-bs-toggle="collapse" href="#v2LogsMenu" role="button" aria-expanded="false" aria-controls="v2LogsMenu">
                                                Logs
                                                <i class="angle fe fe-chevron-down"></i>
                                            </a>
                                            <ul class="collapse ps-3" id="v2LogsMenu">
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/logs/stock-sync')}}">
                                                        Stock Sync
                                                    </a>
                                                </li>
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/logs/log-file')}}">
                                                        Log File
                                                    </a>
                                                </li>
                                                <li class="slide">
                                                    <a class="side-menu__item ps-0" href="{{url('v2/logs/failed-jobs')}}">
                                                        Failed Jobs
                                                    </a>
                                                </li>
                                            </ul>
                                        </li>
                                        @endif
                                    </ul>
                                </li>
                                @endif
								<li class="slide">
                                    <a class="side-menu__item" href="{{url('index')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                        <span class="side-menu__label">Dashboard</span></a>
								</li>
                                @if($user->hasPermission('view_report'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('report')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                                        <span class="side-menu__label">Reports</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_listing'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('v2/listings')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                                        <span class="side-menu__label">Listings</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_topup'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('topup')}}?status=2"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                        <span class="side-menu__label">Topups</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_purchase'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('purchase').'?status=3&stock=1'}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                        <span class="side-menu__label">Purchases</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_rma'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('rma').'?status=2'}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                                        <span class="side-menu__label">RMA</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_order'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('order')}}" wire:navigate><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                        <span class="side-menu__label">Sales</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('dispatch_admin'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('sales/allowed')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                        <span class="side-menu__label">Sales (Admin)</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_return'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('return')}}?status=1"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                        <span class="side-menu__label">Sales Returns</span></a>
								</li>
                                @endif
                                <li class="slide">
                                <a class="side-menu__item" href="{{ url('support') }}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg>
                                    <span class="side-menu__label">Support</span></a>
                            </li>
                                @if($user->hasPermission('view_wholesale'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('wholesale').'?status=2'}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                        <span class="side-menu__label">BulkSale</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_wholesale_return'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('wholesale_return')}}?status=1"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                                        <span class="side-menu__label">BulkSale Returns</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('pos'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('pos')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                        <span class="side-menu__label">POS</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_inventory'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('inventory')}}?status=3&grade[]=1&grade[]=2&grade[]=3&grade[]=5&grade[]=7&grade[]=9"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                                        <span class="side-menu__label">Inventory</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_belfast_inventory'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('belfast_inventory')}}?status=2"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                        <span class="side-menu__label">Belfast Inventory</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_inventory_verification'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('inventory_verification')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                        <span class="side-menu__label">Inventory Verification</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('inventory_verification_scan'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('inventory/verification')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><polyline points="9 14 11 16 15 12"></polyline></svg>
                                        <span class="side-menu__label">Verify Inventory</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_stock_room'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('stock_room')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path></svg>
                                        <span class="side-menu__label">Stock Room</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_variation'))
								<li class="slide">
									<a class="side-menu__item" href="{{url('variation')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line><line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line><line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line><line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line></svg>
                                        <span class="side-menu__label">Product Variations</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_b2b_customer'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('customer')}}?type=2"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                        <span class="side-menu__label">B2B Customer</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_transaction'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('transaction')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                        <span class="side-menu__label">Transactions</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_bm_invoice'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('bm_invoice')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                        <span class="side-menu__label">BM Invoices</span></a>
								</li>
                                @endif

                                @if($user->hasPermission('view_product') || $user->hasPermission('view_grade') || $user->hasPermission('view_role') || $user->hasPermission('view_color') || $user->hasPermission('view_storage') || $user->hasPermission('view_team') || $user->hasPermission('view_customer') || $user->hasPermission('view_b2b_customer') || $user->hasPermission('view_charge'))
                                <li class="slide has-sub">
                                    <a class="side-menu__item" data-bs-toggle="collapse" href="#optionsMenu" role="button" aria-expanded="false" aria-controls="optionsMenu">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6m5.66-13a10 10 0 0 1 0 14M6.34 6a10 10 0 0 0 0 14M18 12h6M0 12h6"></path></svg>
                                        <span class="side-menu__label">Options</span>
                                        <i class="angle fe fe-chevron-down"></i>
                                    </a>
                                    <ul class="collapse ps-5" id="optionsMenu">
                                        @if ($user->hasPermission('view_customer'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('customer')}}">Customer</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_product'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('product')}}">Products</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_category'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('category')}}">Categories</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_brand'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('brand')}}">Brands</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_grade'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('grade')}}">Grades</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_color'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('color')}}">Colors</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_storage'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('storage')}}">Storages</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_team'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('team')}}">Team</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_role'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('role')}}">Roles</a>
                                        </li>
                                        @endif
                                        @if ($user->hasPermission('view_charge'))
                                        <li class="slide">
                                            <a class="side-menu__item ps-0" href="{{url('charge')}}">Charges</a>
                                        </li>
                                        @endif
                                    </ul>
                                </li>
                                @endif
                                @if($user->hasPermission('view_testing_api_data'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('testing')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"></polyline><line x1="12" y1="19" x2="20" y2="19"></line></svg>
                                        <span class="side-menu__label">Testing API Data</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_fortnight_return'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('fortnight_return')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                        <span class="side-menu__label">Fortnight Returns</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_repair'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('repair')}}?status=2"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                                        <span class="side-menu__label">External Repairs</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('internal_repair'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('repair/internal')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                                        <span class="side-menu__label">Internal Repairs</span></a>
								</li>
                                @endif
                                {{-- @if($user->hasPermission('view_charge'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('charge')}}"><svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" class="side-menu__icon" width="24" height="24"viewBox="0 0 48 48"><path d="M155.469,79.103C155.009,79.037 154.52,79 154,79C150.17,79 148.031,81.021 147.211,82.028C147.078,82.201 147.007,82.413 147.007,82.632C147.007,82.638 147.007,82.644 147.006,82.649C147,83.019 147,83.509 147,84C147,84.552 147.448,85 148,85L155.172,85C155.059,84.682 155,84.344 155,84C155,84 155,84 155,84C155,82.862 155,81.506 155.004,80.705C155.004,80.135 155.167,79.58 155.469,79.103ZM178,85L158,85C157.735,85 157.48,84.895 157.293,84.707C157.105,84.52 157,84.265 157,84C157,82.865 157,81.515 157.004,80.711C157.004,80.709 157.004,80.707 157.004,80.705C157.004,80.475 157.084,80.253 157.229,80.075C158.47,78.658 162.22,75 168,75C174.542,75 177.827,78.651 178.832,80.028C178.943,80.197 179,80.388 179,80.583L179,84C179,84.265 178.895,84.52 178.707,84.707C178.52,84.895 178.265,85 178,85ZM180.828,85L188,85C188.552,85 189,84.552 189,84L189,82.631C189,82.41 188.927,82.196 188.793,82.021C187.969,81.021 185.829,79 182,79C181.507,79 181.042,79.033 180.604,79.093C180.863,79.546 181,80.06 181,80.585L181,84C181,84.344 180.941,84.682 180.828,85ZM154,67C151.24,67 149,69.24 149,72C149,74.76 151.24,77 154,77C156.76,77 159,74.76 159,72C159,69.24 156.76,67 154,67ZM182,67C179.24,67 177,69.24 177,72C177,74.76 179.24,77 182,77C184.76,77 187,74.76 187,72C187,69.24 184.76,67 182,67ZM168,59C164.137,59 161,62.137 161,66C161,69.863 164.137,73 168,73C171.863,73 175,69.863 175,66C175,62.137 171.863,59 168,59Z" transform="translate(-144 -48)"/></svg>
                                        <span class="side-menu__label">Charges</span></a>
								</li>
                                @endif --}}
                                @if($user->hasPermission('move_inventory'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('move_inventory')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><polyline points="5 9 2 12 5 15"></polyline><polyline points="9 5 12 2 15 5"></polyline><polyline points="15 19 12 22 9 19"></polyline><polyline points="19 9 22 12 19 15"></polyline><line x1="2" y1="12" x2="22" y2="12"></line><line x1="12" y1="2" x2="12" y2="22"></line></svg>
                                        <span class="side-menu__label">Move Inventory</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_imei'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url('imei')}}"><svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="url(#iconGradient)" stroke-width="1.75" opacity="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
                                        <span class="side-menu__label">Search Serial</span></a>
								</li>
                                @endif
							</ul>
							<div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"><path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"/></svg></div>
						</div>
					</aside>
				</div>
				<!-- main-sidebar -->
