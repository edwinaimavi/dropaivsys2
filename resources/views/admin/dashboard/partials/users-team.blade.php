<section class="dashboard-section team-section" aria-labelledby="dashboard-team-title">
    <div class="section-heading team-section__heading">
        <div>
            <span>Personas y acceso</span>
            <h2 id="dashboard-team-title">Equipo registrado</h2>
        </div>
        <div class="team-section__summary">
            <p>Usuarios con acceso al sistema</p>
            <span class="team-section__count">
                <i class="fas fa-users" aria-hidden="true"></i>
                {{ $dashboardUsersTotal }} {{ $dashboardUsersTotal === 1 ? 'usuario' : 'usuarios' }}
            </span>
        </div>
    </div>

    @if ($dashboardUsers->isNotEmpty())
        <div class="team-grid">
            @foreach ($dashboardUsers as $dashboardUser)
                @php
                    $nameParts = preg_split('/\s+/u', trim((string) $dashboardUser->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $lastnameParts = preg_split('/\s+/u', trim((string) $dashboardUser->lastname), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $firstName = $nameParts[0] ?? 'Usuario';
                    $secondInitialSource = $lastnameParts[0] ?? ($nameParts[1] ?? '');
                    $initials = mb_strtoupper(
                        mb_substr($firstName, 0, 1) . mb_substr($secondInitialSource, 0, 1)
                    );
                    $roleName = $dashboardUser->roles->first()?->name ?? 'Sin rol';
                    $isActive = (int) $dashboardUser->status === 1;
                @endphp

                <article class="team-member">
                    <div class="team-member__avatar" aria-hidden="true">
                        <span>{{ $initials ?: 'U' }}</span>
                        @if ($dashboardUser->photo)
                            <img src="{{ Storage::url($dashboardUser->photo) }}"
                                alt=""
                                loading="lazy"
                                onerror="this.remove()">
                        @endif
                    </div>

                    <div class="team-member__identity">
                        <strong title="{{ $firstName }}">{{ $firstName }}</strong>
                        <span class="team-member__role" title="{{ $roleName }}">
                            <i class="fas fa-id-badge" aria-hidden="true"></i>
                            {{ $roleName }}
                        </span>
                    </div>

                    <span class="team-member__status team-member__status--{{ $isActive ? 'active' : 'inactive' }}">
                        <i aria-hidden="true"></i>
                        {{ $isActive ? 'Activo' : 'Inactivo' }}
                    </span>
                </article>
            @endforeach
        </div>

        @if ($dashboardUsersTotal > $dashboardUsers->count())
            <div class="team-section__more">
                <i class="fas fa-user-plus" aria-hidden="true"></i>
                + {{ $dashboardUsersTotal - $dashboardUsers->count() }} m&aacute;s
            </div>
        @endif
    @else
        <div class="team-section__empty">
            <i class="fas fa-user-friends" aria-hidden="true"></i>
            <div>
                <strong>A&uacute;n no hay usuarios para mostrar</strong>
                <span>Los usuarios registrados aparecer&aacute;n en esta secci&oacute;n.</span>
            </div>
        </div>
    @endif
</section>
