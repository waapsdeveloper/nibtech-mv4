				<!-- main-sidebar -->
				<div class="sticky">
					<aside class="app-sidebar">
						<div class="main-sidebar-header active">
							<a class="header-logo active" href="{{url(session('url').'index')}}">
								<img src="{{asset('assets/img/brand/logo1.png')}}" class="main-logo  desktop-logo" alt="logo" height="150" width="150">
								<img src="{{asset('assets/img/brand/logo-white1.png')}}" class="main-logo  desktop-dark" alt="logo" height="150" width="150">
								<img src="{{asset('assets/img/brand/favicon1.png')}}" class="main-logo  mobile-logo" alt="logo">
								<img src="{{asset('assets/img/brand/favicon-white1.png')}}" class="main-logo  mobile-dark" alt="logo">
							</a>
						</div>
						<div class="main-sidemenu">
							<div class="slide-left disabled" id="slide-left"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"><path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"/></svg></div>
							<ul class="side-menu">
								<li class="side-item side-item-category">Main</li>
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'index')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M3 13h1v7c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2v-7h1a1 1 0 0 0 .707-1.707l-9-9a.999.999 0 0 0-1.414 0l-9 9A1 1 0 0 0 3 13zm7 7v-5h4v5h-4zm2-15.586 6 6V15l.001 5H16v-5c0-1.103-.897-2-2-2h-4c-1.103 0-2 .897-2 2v5H6v-9.586l6-6z"/></svg>
                                        <span class="side-menu__label">Dashboard</span></a>
								</li>
                                @php
                                $user = session('user');
                                @endphp
                                @if($user->hasPermission('view_purchase'))
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'purchase')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM9 9H5V5h4v4zm11 4h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm-1 6h-4v-4h4v4zM17 3c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2zM7 13c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2z"/></svg>
                                        <span class="side-menu__label">Purchases</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_rma'))
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'rma')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM9 9H5V5h4v4zm11 4h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm-1 6h-4v-4h4v4zM17 3c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2zM7 13c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2z"/></svg>
                                        <span class="side-menu__label">RMA</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_order'))
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'order')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM9 9H5V5h4v4zm11 4h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm-1 6h-4v-4h4v4zM17 3c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2zM7 13c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2z"/></svg>
                                        <span class="side-menu__label">Sales</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_bulksale'))
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'wholesale')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM9 9H5V5h4v4zm11 4h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm-1 6h-4v-4h4v4zM17 3c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2zM7 13c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2z"/></svg>
                                        <span class="side-menu__label">BulkSale</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_inventory'))
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'inventory')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM9 9H5V5h4v4zm11 4h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm-1 6h-4v-4h4v4zM17 3c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2zM7 13c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2z"/></svg>
                                        <span class="side-menu__label">Inventory</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_product'))
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'product')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM9 9H5V5h4v4zm11 4h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm-1 6h-4v-4h4v4zM17 3c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2zM7 13c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2z"/></svg>
                                        <span class="side-menu__label">Products</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_variation'))
								<li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'variation')}}"><svg xmlns="http://www.w3.org/2000/svg"  class="side-menu__icon" width="24" height="24" viewBox="0 0 24 24"><path d="M10 3H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1zM9 9H5V5h4v4zm11 4h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-6a1 1 0 0 0-1-1zm-1 6h-4v-4h4v4zM17 3c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2zM7 13c-2.206 0-4 1.794-4 4s1.794 4 4 4 4-1.794 4-4-1.794-4-4-4zm0 6c-1.103 0-2-.897-2-2s.897-2 2-2 2 .897 2 2-.897 2-2 2z"/></svg>
                                        <span class="side-menu__label">Product Variations</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_team'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'team')}}"><svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" class="side-menu__icon" width="24" height="24"viewBox="0 0 48 48"><path d="M155.469,79.103C155.009,79.037 154.52,79 154,79C150.17,79 148.031,81.021 147.211,82.028C147.078,82.201 147.007,82.413 147.007,82.632C147.007,82.638 147.007,82.644 147.006,82.649C147,83.019 147,83.509 147,84C147,84.552 147.448,85 148,85L155.172,85C155.059,84.682 155,84.344 155,84C155,84 155,84 155,84C155,82.862 155,81.506 155.004,80.705C155.004,80.135 155.167,79.58 155.469,79.103ZM178,85L158,85C157.735,85 157.48,84.895 157.293,84.707C157.105,84.52 157,84.265 157,84C157,82.865 157,81.515 157.004,80.711C157.004,80.709 157.004,80.707 157.004,80.705C157.004,80.475 157.084,80.253 157.229,80.075C158.47,78.658 162.22,75 168,75C174.542,75 177.827,78.651 178.832,80.028C178.943,80.197 179,80.388 179,80.583L179,84C179,84.265 178.895,84.52 178.707,84.707C178.52,84.895 178.265,85 178,85ZM180.828,85L188,85C188.552,85 189,84.552 189,84L189,82.631C189,82.41 188.927,82.196 188.793,82.021C187.969,81.021 185.829,79 182,79C181.507,79 181.042,79.033 180.604,79.093C180.863,79.546 181,80.06 181,80.585L181,84C181,84.344 180.941,84.682 180.828,85ZM154,67C151.24,67 149,69.24 149,72C149,74.76 151.24,77 154,77C156.76,77 159,74.76 159,72C159,69.24 156.76,67 154,67ZM182,67C179.24,67 177,69.24 177,72C177,74.76 179.24,77 182,77C184.76,77 187,74.76 187,72C187,69.24 184.76,67 182,67ZM168,59C164.137,59 161,62.137 161,66C161,69.863 164.137,73 168,73C171.863,73 175,69.863 175,66C175,62.137 171.863,59 168,59Z" transform="translate(-144 -48)"/></svg>
                                        <span class="side-menu__label">{{ __('locale.Team') }}</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_customer'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'customer')}}"><svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" class="side-menu__icon" width="24" height="24"viewBox="0 0 48 48"><path d="M155.469,79.103C155.009,79.037 154.52,79 154,79C150.17,79 148.031,81.021 147.211,82.028C147.078,82.201 147.007,82.413 147.007,82.632C147.007,82.638 147.007,82.644 147.006,82.649C147,83.019 147,83.509 147,84C147,84.552 147.448,85 148,85L155.172,85C155.059,84.682 155,84.344 155,84C155,84 155,84 155,84C155,82.862 155,81.506 155.004,80.705C155.004,80.135 155.167,79.58 155.469,79.103ZM178,85L158,85C157.735,85 157.48,84.895 157.293,84.707C157.105,84.52 157,84.265 157,84C157,82.865 157,81.515 157.004,80.711C157.004,80.709 157.004,80.707 157.004,80.705C157.004,80.475 157.084,80.253 157.229,80.075C158.47,78.658 162.22,75 168,75C174.542,75 177.827,78.651 178.832,80.028C178.943,80.197 179,80.388 179,80.583L179,84C179,84.265 178.895,84.52 178.707,84.707C178.52,84.895 178.265,85 178,85ZM180.828,85L188,85C188.552,85 189,84.552 189,84L189,82.631C189,82.41 188.927,82.196 188.793,82.021C187.969,81.021 185.829,79 182,79C181.507,79 181.042,79.033 180.604,79.093C180.863,79.546 181,80.06 181,80.585L181,84C181,84.344 180.941,84.682 180.828,85ZM154,67C151.24,67 149,69.24 149,72C149,74.76 151.24,77 154,77C156.76,77 159,74.76 159,72C159,69.24 156.76,67 154,67ZM182,67C179.24,67 177,69.24 177,72C177,74.76 179.24,77 182,77C184.76,77 187,74.76 187,72C187,69.24 184.76,67 182,67ZM168,59C164.137,59 161,62.137 161,66C161,69.863 164.137,73 168,73C171.863,73 175,69.863 175,66C175,62.137 171.863,59 168,59Z" transform="translate(-144 -48)"/></svg>
                                        <span class="side-menu__label">Customer</span></a>
								</li>
                                @endif
                                @if($user->hasPermission('view_imei'))
                                <li class="slide">
									<a class="side-menu__item" href="{{url(session('url').'imei')}}"><svg xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" clip-rule="evenodd" class="side-menu__icon" width="24" height="24"viewBox="0 0 48 48"><path d="M155.469,79.103C155.009,79.037 154.52,79 154,79C150.17,79 148.031,81.021 147.211,82.028C147.078,82.201 147.007,82.413 147.007,82.632C147.007,82.638 147.007,82.644 147.006,82.649C147,83.019 147,83.509 147,84C147,84.552 147.448,85 148,85L155.172,85C155.059,84.682 155,84.344 155,84C155,84 155,84 155,84C155,82.862 155,81.506 155.004,80.705C155.004,80.135 155.167,79.58 155.469,79.103ZM178,85L158,85C157.735,85 157.48,84.895 157.293,84.707C157.105,84.52 157,84.265 157,84C157,82.865 157,81.515 157.004,80.711C157.004,80.709 157.004,80.707 157.004,80.705C157.004,80.475 157.084,80.253 157.229,80.075C158.47,78.658 162.22,75 168,75C174.542,75 177.827,78.651 178.832,80.028C178.943,80.197 179,80.388 179,80.583L179,84C179,84.265 178.895,84.52 178.707,84.707C178.52,84.895 178.265,85 178,85ZM180.828,85L188,85C188.552,85 189,84.552 189,84L189,82.631C189,82.41 188.927,82.196 188.793,82.021C187.969,81.021 185.829,79 182,79C181.507,79 181.042,79.033 180.604,79.093C180.863,79.546 181,80.06 181,80.585L181,84C181,84.344 180.941,84.682 180.828,85ZM154,67C151.24,67 149,69.24 149,72C149,74.76 151.24,77 154,77C156.76,77 159,74.76 159,72C159,69.24 156.76,67 154,67ZM182,67C179.24,67 177,69.24 177,72C177,74.76 179.24,77 182,77C184.76,77 187,74.76 187,72C187,69.24 184.76,67 182,67ZM168,59C164.137,59 161,62.137 161,66C161,69.863 164.137,73 168,73C171.863,73 175,69.863 175,66C175,62.137 171.863,59 168,59Z" transform="translate(-144 -48)"/></svg>
                                        <span class="side-menu__label">Search Serial</span></a>
								</li>
                                @endif
							</ul>
							<div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"><path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"/></svg></div>
						</div>
					</aside>
				</div>
				<!-- main-sidebar -->
