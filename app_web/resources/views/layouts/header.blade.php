 <nav class="layout-navbar navbar navbar-expand-xl align-items-center" id="layout-navbar">
          <div class="container-xxl">
            <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4 ms-0">
              <a href="{{ route('home') }}" class="app-brand-link">
                
                <img style="height: 50px;" src="{{ asset('imagenes/logo.jpg') }}"/> 
              </a>

              <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-xl-none">
                <i class="icon-base ti tabler-x icon-sm d-flex align-items-center justify-content-center"></i>
              </a>
            </div>

            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0  d-xl-none  ">
              <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                <i class="icon-base ti tabler-menu-2 icon-md"></i>
              </a>
            </div>

            <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
              <ul class="navbar-nav flex-row align-items-center ms-md-auto">
               
                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a
                    class="nav-link dropdown-toggle hide-arrow p-0"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                      <img src="{{ asset('assets/img/avatars/1.png') }}" alt class="rounded-circle" />
                    </div>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item mt-0" href="#">
                        <div class="d-flex align-items-center">
                          <div class="flex-shrink-0 me-2">
                            <div class="avatar avatar-online">
                              <img src="{{ asset('assets/img/avatars/1.png') }}" alt class="rounded-circle" />
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            <h6 class="mb-0">{{ Auth::user()->name }}</h6>
                            <small class="text-body-secondary"> @if(auth()->user()->hasRole('admin'))
                          <p>Administrador</p>
                      @elseif(auth()->user()->hasRole('Mesero'))
                          <p>Mesero</p>
                      @endif</small>
                          </div>
                        </div>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider my-1 mx-n2"></div>
                    </li>
                    
                    <li>
                      <div class="d-grid px-2 pt-2 pb-1">
                        <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();" class="btn btn-sm btn-danger d-flex">
                        
                        <small class="align-middle">Salir</small>
                        <i class="fa-solid fa-arrow-right-from-bracket" style="margin-left: 10px;"></i>
                    </x-responsive-nav-link>
                </form>
                      </div>
                    </li>
                  </ul>
                </li>
                <!--/ User -->
              </ul>
            </div>
          </div>
        </nav>


