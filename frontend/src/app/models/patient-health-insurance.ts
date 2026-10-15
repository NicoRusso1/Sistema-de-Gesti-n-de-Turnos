import { HealthInsurance } from './health-insurance';

export interface PatientHealthInsurance {
  id: number;
  patient_id: number;
  health_insurance_id: number;
  member_number: string | null;
  health_insurance?: HealthInsurance;
}

export interface PatientHealthInsuranceResponse {
  affiliation: PatientHealthInsurance | null;
}

export interface PatientHealthInsuranceSaveResponse {
  message: string;
  affiliation: PatientHealthInsurance;
}