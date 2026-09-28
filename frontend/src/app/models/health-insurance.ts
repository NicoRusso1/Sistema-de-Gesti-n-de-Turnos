export interface HealthInsurance {
    id: number;
    name: string;
    discount_percentage: string;
    status: number;
}

export interface HealthInsuranceFormData {
    name: string;
    discount_percentage: number;
    status: number;
}