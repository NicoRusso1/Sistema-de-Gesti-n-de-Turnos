export interface DoctorSchedule {
  id: number;
  doctor_id: number;
  day_of_week: number;
  start_time: string;
  end_time: string;
}

export interface DoctorScheduleFormData {
  day_of_week: number;
  start_time: string;
  end_time: string;
}

export interface DoctorSlot {
  start_time: string;
  end_time: string;
}

export interface DoctorSlots {
  doctor_id: number;
  date: string;
  duration: number;
  slots: DoctorSlot[];
}