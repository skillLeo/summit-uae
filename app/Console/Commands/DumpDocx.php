<?php

namespace App\Console\Commands;

use App\Content\Docx\DocxReader;
use Illuminate\Console\Command;

final class DumpDocx extends Command
{
    protected $signature = 'summit:docx-dump {file : Path to a .docx file} {--locale=en} {--json : Print blocks as JSON}';

    protected $description = 'Print a DOCX as an annotated block stream ([[RED: …]] marks designer notes)';

    public function handle(DocxReader $reader): int
    {
        $path = (string) $this->argument('file');
        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }
        $doc = $reader->read($path, (string) $this->option('locale'));
        if ($this->option('json')) {
            $this->output->writeln(json_encode(array_map(fn ($b) => $b->toArray(), $doc->blocks), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
        } else {
            $this->output->write($doc->toAnnotatedText(), false, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
        }

        return self::SUCCESS;
    }
}
