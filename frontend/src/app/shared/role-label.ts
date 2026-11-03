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
