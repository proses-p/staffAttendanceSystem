<?php

namespace App\Console\Commands;

use App\Mail\MissedAttendanceMail;
use App\Mail\NearOfficeMail;
use App\Models\Attendance;
//se App\Models\OfficeLocation;
use App\Models\User;
use Illuminate\Console\Command;
//use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class SendAttendanceNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-attendance-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automatic staff attendance notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //$office = OfficeLocation::first();
        // if (!$office) {
        //     $this->error('Office location has not been configured yet.');
        //     return Command::FAILURE;
        // }
        $staffMembers = User::where('role', 'staff')->get();
        $currentTime = now()->format('H:i');
        foreach ($staffMembers as $staff) {
            // staff already sign ed in today
            $alreadySignedIn = Attendance::where('user_id', $staff->id)
            ->whereDate('attendance_date', today())
            ->exists();

            if ($currentTime === '07:55' && !$alreadySignedIn) {
                Mail::to($staff->email)
                    ->send(new NearOfficeMail($staff->name));
                $this->info(
                    "Reminder email sent to {$staff->email}"
                );
            }

            // get latest temporary location from cache
            
            // if (!$location) {
            //     continue;
            // }
            // $distance = $this->calculateDistance(
            //     $location['latitude'],
            //     $location['longitude'],
            //     $office->latitude,
            //     $office->longitude,
            // );

            // // staff is near the office 
            // if ($distance <= $office->allowed_radius) {
            //     Mail::to($staff->email)
            //         ->send(new NearOfficeMail($staff->name));
            //     $this->info(
            //         "Near-office email sent to {$staff->email}"
            //     );
            // }
        }

        if ($currentTime === '08:30' && !$alreadySignedIn) {
                Mail::to($staff->email)
                    ->send(new MissedAttendanceMail($staff->name));
                $this->info(
                    "Missed attendance email sent to {$staff->email}"
                );
        }
        return Command::SUCCESS;
    }

    // DISTANCE CALCULATION

    public function calculateDistance(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadius = 6371000;
        $latDifference = deg2rad($latitude2 - $latitude1);
        $lonDifference = deg2rad($longitude2 - $longitude1);

        $a = 
            sin($latDifference / 2) ** 2 +
            cos(deg2rad($latitude1)) *
            cos(deg2rad($latitude2)) *
            sin($lonDifference / 2) ** 2;

        $c = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadius * $c;

        
    }
}
