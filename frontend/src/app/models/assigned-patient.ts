export interface AssignedPatient {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string | null;
    health_insurance_number: string | null;
    appointments_count: number;
    personal_data: { id: number; patient_id: number; national_id: string } | null;
    health_insurance: { id: number; name: string; discount_percentage: string } | null;
}