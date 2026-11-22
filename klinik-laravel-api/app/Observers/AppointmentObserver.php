<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\DoctorTime;
use Illuminate\Support\Facades\DB;

class AppointmentObserver
{
    public function updated(Appointment $appointment): void
    {
        if ($appointment->wasChanged('general_status') && $appointment->general_status === 'canceled') {
            $this->releaseDate($appointment);
        }
    }

    public function deleted(Appointment $appointment): void
    {
        $this->releaseDate($appointment);
    }

    private function releaseDate(Appointment $appointment): void
    {
        DB::transaction(function () use ($appointment) {
            $slot = DoctorTime::where('doctor_id', $appointment->doctor_id)
                ->where('day', $appointment->day)->where('start_time', $appointment->start_time)
                ->lockForUpdate()->first();
            if (!$slot) {
                return;
            }
            $occupied = Appointment::where('doctor_id', $appointment->doctor_id)
                ->where('date', $appointment->date)->where('start_time', $appointment->start_time)
                ->where('general_status', '!=', 'canceled')->exists();
            if (!$occupied) {
                $slot->update(['date' => array_values(array_filter(
                    $slot->date ?? [], fn ($date) => $date !== $appointment->date
                ))]);
            }
        });
    }
}
