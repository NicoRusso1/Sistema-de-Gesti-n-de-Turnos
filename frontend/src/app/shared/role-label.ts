interface RoleSource {
  role?: { name: string } | null;
  user_type?: { name: string } | null;
}

export function roleLabel(user: RoleSource | null | undefined): string {
  if (!user) return '';
  switch (user.role?.name) {
    case 'SuperAdmin':
      return 'Superadministrador';
    case 'Administrator':
      return 'Administrador';
  }
  switch (user.user_type?.name) {
    case 'patient':
      return 'Paciente';
    case 'doctor':
      return 'Médico';
    case 'secretary':
      return 'Secretario/a';
  }
  return user.role?.name ?? '';
}

export function tipoLabel(name: string | null | undefined): string {
  switch (name) {
    case 'patient':
      return 'Paciente';
    case 'doctor':
      return 'Doctor';
    case 'secretary':
      return 'Secretario/a';
  }
  return name ?? 'Sin tipo';
}

export function rolLabel(name: string | null | undefined, isOwner = false): string {
  switch (name) {
    case 'SuperAdmin':
      return 'Super Administrador';
    case 'Administrator':
      return isOwner ? 'Administrador propietario' : 'Administrador';
    case 'OperationalUser':
      return 'Usuario operativo';
  }
  return name ?? '';
}
