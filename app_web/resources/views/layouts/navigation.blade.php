
<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu flex-grow-0">
              <div class="container-xxl d-flex h-100">
                <ul class="menu-inner">
                  <!-- Dashboards -->
                  <li class="menu-item">
                    <a href="/dashboard" class="menu-link">
                      <i class="menu-icon icon-base ti tabler-smart-home"></i>
                      <div data-i18n="Inicio">Inicio</div>
                    </a>
                  </li>
                  
                  @can('Administracion')
                  <li class="menu-item">
                    <a href="javascript:void(0)" class="menu-link menu-toggle">
                      <i class="menu-icon fa-solid fa-sliders"></i>
                      <div data-i18n="Administración">Administración</div>
                    </a>
                   
                    <ul class="menu-sub">
                       @can('Ver Usuarios')
                      <li class="menu-item">
                        <a href="/users" class="menu-link">
                          <i class="menu-icon fa-solid fa-users"></i>
                          <div data-i18n="Usuarios">Usuarios</div>
                        </a>
                      </li>
                      @endcan
                     
                      
                      @can('Editar Permisos')
                      <li class="menu-item">
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                          <i class="menu-icon fa-solid fa-user-shield"></i>
                          <div data-i18n="Permisos">Permisos</div>
                        </a>
                        <ul class="menu-sub">
                          <input type="hidden" value="{{$roles = Spatie\Permission\Models\Role::get()}}">
                          @foreach ($roles as $role)
                          <li class="menu-item">
                            <a href="/roles/{{ $role->id }}/permissions/edit" class="menu-link">
                              <div data-i18n="{{ $role->name }}">{{ $role->name }}</div>
                            </a>
                          </li>
                          @endforeach
                        
                        </ul>
                      </li>
                      @endcan
                      


                    </ul>
                  </li>
                  @endcan
                 
                  @can('Gestionar Configuracion')
                  <!-- Layouts -->
                  <li class="menu-item">
                    <a href="javascript:void(0)" class="menu-link menu-toggle">
                      <i class="menu-icon fa-solid fa-gears"></i>
                      <div data-i18n="Configuración">Configuración</div>
                    </a>

                    <ul class="menu-sub">
                      <li class="menu-item">
                        <a href="/companies" class="menu-link">
                          <i class="menu-icon fa-solid fa-building"></i>
                          <div data-i18n="Empresas">Empresas</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('admin.credit-applications.index') }}" class="menu-link">
                          <i class="menu-icon fa-solid fa-file-signature"></i>
                          <div data-i18n="Solicitudes Crédito">Solicitudes Crédito</div>
                        </a>
                      </li>
                      <li class="menu-item">
                        <a href="{{ route('admin.credit-payments.index') }}" class="menu-link">
                          <i class="menu-icon fa-solid fa-money-check-dollar"></i>
                          <div data-i18n="Pagos Crédito">Pagos Crédito</div>
                        </a>
                      </li>

                      <li class="menu-item">
                        <a href="{{ route('admin.platform.index') }}" class="menu-link">
                          <i class="menu-icon fa-solid fa-chart-pie"></i>
                          <div data-i18n="Panel Plataforma">Panel Plataforma</div>
                        </a>
                      </li>
                    </ul>
                  </li>
                  @endcan
                </ul>
              </div>
            </aside>


