<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EventImageSeeder extends Seeder
{
    private const PLACEHOLDERS = [
        'placeholder-1.svg',
        'placeholder-2.svg',
        'placeholder-3.svg',
    ];

    private const CHUNK = 5000;

    public function run(): void
    {
        $this->command?->info('Seeding event images (2 per event)...');

        $start = microtime(true);
        $total = 0;

        DB::table('event_images')->truncate();

        DB::table('events')
            ->select('id')
            ->orderBy('id')
            ->chunk(self::CHUNK, function ($events) use (&$total) {
                $batch = [];
                $now = date('Y-m-d H:i:s');
                $count = count(self::PLACEHOLDERS);

                foreach ($events as $i => $event) {
                    $batch[] = [
                        'event_id' => $event->id,
                        'filename' => self::PLACEHOLDERS[$i % $count],
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $batch[] = [
                        'event_id' => $event->id,
                        'filename' => self::PLACEHOLDERS[($i + 1) % $count],
                        'sort_order' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('event_images')->insert($batch);
                $total += count($events);
            });

        $elapsed = round(microtime(true) - $start, 1);
        $this->command?->info("Done. {$total} events imaged in {$elapsed}s.");
    }
}
