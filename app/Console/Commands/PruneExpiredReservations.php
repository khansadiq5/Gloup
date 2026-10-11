<?php

namespace App\Console\Commands;

use App\Models\SlotReservation;
use App\Services\AvailabilityService;
use Illuminate\Console\Command;

class PruneExpiredReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:prune-expired
                            {--dry-run : Show how many reservations would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired slot reservations (older than 10 minutes)';

    public function __construct(
        protected AvailabilityService $availabilityService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $count = SlotReservation::where('expires_at', '<=', now())->count();
            $this->info("Dry run: {$count} expired reservation(s) would be deleted.");

            return Command::SUCCESS;
        }

        $deleted = $this->availabilityService->pruneExpiredReservations();

        $this->info("Deleted {$deleted} expired reservation(s).");

        return Command::SUCCESS;
    }
}
