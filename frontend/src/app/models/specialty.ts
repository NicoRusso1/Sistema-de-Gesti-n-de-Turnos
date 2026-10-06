export interface Specialty {
  id: number;
  name: string;
  description: string | null;
}

export interface SpecialtyFormData {
  name: string;
  description: string;
}
