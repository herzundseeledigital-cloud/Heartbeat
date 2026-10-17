<?php
declare(strict_types=1);

use Heartbeat\Security\Security;
use Heartbeat\Content\PostService;
use Heartbeat\Content\MetaService;

include __DIR__ . '/header.php';

$postsService = PostService::getInstance();
$metaService = MetaService::getInstance();

$dbTeam = $postsService->query([
    'post_type' => 'team_member',
    'status'    => 'publish',
    'limit'     => 50,
]);

$teamMembers = [];
if (!empty($dbTeam)) {
    foreach ($dbTeam as $member) {
        $meta = $metaService->getAllPostMeta((int)$member['id']);
        $teamMembers[] = [
            'id'         => $member['id'],
            'title'      => $member['title'],
            'slug'       => $member['slug'],
            'position'   => $meta['position'] ?? 'Consultant',
            'department' => $meta['department'] ?? 'Beratung',
            'email'      => $meta['email'] ?? 'team@agentur.de',
            'linkedin'   => $meta['linkedin'] ?? '#',
        ];
    }
} else {
    $teamMembers = [
        ['id' => 1, 'title' => 'Dr. Alexander Becker', 'slug' => 'alexander-becker', 'position' => 'Managing Director & CEO', 'department' => 'Geschäftsführung', 'email' => 'becker@agentur.de', 'linkedin' => 'https://linkedin.com'],
        ['id' => 2, 'title' => 'Sarah Lindemann', 'slug' => 'sarah-lindemann', 'position' => 'Head of Cloud Architecture', 'department' => 'Software Engineering', 'email' => 'lindemann@agentur.de', 'linkedin' => 'https://linkedin.com'],
        ['id' => 3, 'title' => 'Marco Rossi', 'slug' => 'marco-rossi', 'position' => 'Principal UX Strategist', 'department' => 'UI/UX Design', 'email' => 'rossi@agentur.de', 'linkedin' => 'https://linkedin.com'],
        ['id' => 4, 'title' => 'Elena Rostova', 'slug' => 'elena-rostova', 'position' => 'Senior DevOps & SecOps Engineer', 'department' => 'Software Engineering', 'email' => 'rostova@agentur.de', 'linkedin' => 'https://linkedin.com'],
        ['id' => 5, 'title' => 'Felix Hartmann', 'slug' => 'felix-hartmann', 'position' => 'Director Business Development', 'department' => 'Marketing & Sales', 'email' => 'hartmann@agentur.de', 'linkedin' => 'https://linkedin.com'],
        ['id' => 6, 'title' => 'Laura Weber', 'slug' => 'laura-weber', 'position' => 'Client Success Manager', 'department' => 'Kundenberatung', 'email' => 'weber@agentur.de', 'linkedin' => 'https://linkedin.com'],
    ];
}
?>

<main class="<?= $containerWidth ?> mx-auto px-6 py-16 flex-grow w-full" x-data="{ selectedDept: 'all' }">
    <div class="mb-12 pb-6 border-b border-[#1e293b]">
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-blue-500/10 text-blue-400 text-xs font-semibold mb-3">
            <span>Ansprechpartner</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">Unser Team &amp; Führungskräfte</h1>
        <p class="text-sm text-slate-400 mt-2 max-w-2xl">
            Lernen Sie die Köpfe hinter unseren erfolgreichen Enterprise-Projekten kennen. Direkte Ansprechpartner für jede Projektphase.
        </p>

        <!-- Department Filter Tabs -->
        <div class="flex flex-wrap items-center gap-2 mt-8">
            <button @click="selectedDept = 'all'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition"
                    :class="selectedDept === 'all' ? 'bg-blue-600 text-white' : 'bg-[#0f172a] text-slate-400 hover:text-white'">
                Alle Abteilungen
            </button>
            <button @click="selectedDept = 'Geschäftsführung'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition"
                    :class="selectedDept === 'Geschäftsführung' ? 'bg-blue-600 text-white' : 'bg-[#0f172a] text-slate-400 hover:text-white'">
                Geschäftsführung
            </button>
            <button @click="selectedDept = 'Software Engineering'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition"
                    :class="selectedDept === 'Software Engineering' ? 'bg-blue-600 text-white' : 'bg-[#0f172a] text-slate-400 hover:text-white'">
                Software Engineering
            </button>
            <button @click="selectedDept = 'UI/UX Design'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition"
                    :class="selectedDept === 'UI/UX Design' ? 'bg-blue-600 text-white' : 'bg-[#0f172a] text-slate-400 hover:text-white'">
                UI/UX Design
            </button>
            <button @click="selectedDept = 'Marketing & Sales'"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition"
                    :class="selectedDept === 'Marketing & Sales' ? 'bg-blue-600 text-white' : 'bg-[#0f172a] text-slate-400 hover:text-white'">
                Marketing &amp; Sales
            </button>
        </div>
    </div>

    <!-- Team Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($teamMembers as $tm): ?>
            <div x-show="selectedDept === 'all' || selectedDept === '<?= Security::e((string)$tm['department']) ?>'"
                 class="p-6 rounded-2xl bg-[#0f172a] border border-[#1e293b] hover:border-blue-500/40 transition">
                <div class="w-16 h-16 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center font-bold text-xl mb-4">
                    <?= mb_substr((string)$tm['title'], 0, 1) ?>
                </div>
                <div class="text-base font-bold text-white"><?= Security::e((string)$tm['title']) ?></div>
                <div class="text-xs text-blue-400 font-medium mt-0.5"><?= Security::e((string)$tm['position']) ?></div>
                <div class="inline-block mt-2 px-2.5 py-0.5 rounded-full bg-slate-800 text-[10px] text-slate-400">
                    <?= Security::e((string)$tm['department']) ?>
                </div>

                <div class="mt-6 pt-4 border-t border-[#1e293b] flex items-center justify-between text-xs">
                    <a href="mailto:<?= Security::e((string)$tm['email']) ?>" class="text-slate-400 hover:text-white transition flex items-center space-x-1">
                        <span>✉</span>
                        <span><?= Security::e((string)$tm['email']) ?></span>
                    </a>
                    <?php if (!empty($tm['linkedin'])): ?>
                        <a href="<?= Security::e((string)$tm['linkedin']) ?>" target="_blank" rel="noopener" class="text-blue-400 hover:text-blue-300 font-semibold">LinkedIn</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

