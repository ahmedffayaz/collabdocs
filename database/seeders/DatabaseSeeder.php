<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Document;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::updateOrCreate(['email' => 'alex@ajaia.test'], ['name' => 'Alex Morgan', 'password' => 'password']);
        $writer = User::updateOrCreate(['email' => 'sam@ajaia.test'], ['name' => 'Sam Rivera', 'password' => 'password']);
        User::updateOrCreate(['email' => 'jordan@ajaia.test'], ['name' => 'Jordan Lee', 'password' => 'password']);

        if (! Document::where('owner_id', $owner->id)->exists()) {
            $doc = Document::create([
                'owner_id' => $owner->id,
                'title' => 'A better way to work together',
                'content' => '<h2>Make space for the work that matters.</h2><p>Good work happens when teams have a little room to think, write, and build together.</p><p>This is a shared document. Try switching accounts in the bottom left to see how collaboration feels from another point of view.</p><ul><li>Capture the idea while it is fresh</li><li>Invite a teammate into the conversation</li><li>Keep moving forward, together</li></ul>',
            ]);
            $doc->collaborators()->attach($writer->id, ['permission' => 'editor']);
        }
    }
}
