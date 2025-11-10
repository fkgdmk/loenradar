<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchRedditPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reddit:fetch-posts {--limit=10 : Antal posts der skal hentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hent de seneste posts fra r/dkloenseddel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->option('limit');
        
        $this->info("Henter de seneste {$limit} posts fra r/dkloenseddel...");

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('services.reddit.user_agent', 'Laravel/1.0'),
            ])->get('https://www.reddit.com/r/dkloenseddel.json', [
                'limit' => $limit,
            ]);

            if ($response->failed()) {
                $this->error('Kunne ikke hente posts fra Reddit API');
                $this->error("Status: {$response->status()}");
                return Command::FAILURE;
            }

            $data = $response->json();
            $posts = $data['data']['children'] ?? [];

            if (empty($posts)) {
                $this->warn('Ingen posts fundet');
                return Command::SUCCESS;
            }

            $this->info("Fandt {$this->count($posts)} posts:\n");

            foreach ($posts as $index => $item) {
                $post = $item['data'];
                
                $this->line('─────────────────────────────────────────────────────');
                $this->line(sprintf(
                    "<fg=cyan>%d. %s</>",
                    $index + 1,
                    $post['title']
                ));
                $this->line(sprintf(
                    "   <fg=gray>Forfatter:</> %s | <fg=gray>Score:</> %d | <fg=gray>Kommentarer:</> %d",
                    $post['author'],
                    $post['score'],
                    $post['num_comments']
                ));
                $this->line(sprintf(
                    "   <fg=gray>Oprettet:</> %s",
                    date('Y-m-d H:i:s', $post['created_utc'])
                ));
                $this->line(sprintf(
                    "   <fg=gray>URL:</> https://reddit.com%s",
                    $post['permalink']
                ));
                
                if (!empty($post['selftext'])) {
                    $preview = mb_substr($post['selftext'], 0, 100);
                    if (mb_strlen($post['selftext']) > 100) {
                        $preview .= '...';
                    }
                    $this->line(sprintf(
                        "   <fg=gray>Tekst:</> %s",
                        $preview
                    ));
                }

                if (!empty($post['url']) && !str_contains($post['url'], 'reddit.com')) {
                    $this->line(sprintf(
                        "   <fg=gray>Link:</> %s",
                        $post['url']
                    ));
                }
            }

            $this->newLine();
            $this->info('✓ Posts hentet succesfuldt');

            // Log til Laravel log
            Log::info('Reddit posts hentet', [
                'subreddit' => 'dkloenseddel',
                'count' => count($posts),
                'limit' => $limit,
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Der opstod en fejl ved hentning af Reddit posts');
            $this->error($e->getMessage());
            
            Log::error('Fejl ved hentning af Reddit posts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Count the number of posts
     */
    private function count(array $posts): int
    {
        return count($posts);
    }
}

