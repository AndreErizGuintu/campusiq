<?php
/**
 * Landing page (design-ref 01 desktop, 14 mobile).
 * @var array|null $user
 */
use App\Core\Auth;

$features = [
    ['database', 'bg-ink/[.07] text-ink', 'Records in one place', 'Grades, attendance and library records together. Built to work with whatever dataset the school uses.'],
    ['ai', 'bg-primary/10 text-primary', 'Ask in plain language', 'Staff type questions like "who\'s absent today" and get answers and summaries instead of scrolling tables.'],
    ['mail', 'bg-mail/10 text-mail', 'Automatic email alerts', 'Students and parents are emailed the moment a grade is posted, an absence is logged or a book is overdue.'],
    ['pdf', 'bg-pdf/[.08] text-pdf', 'PDF reports on demand', 'Any record becomes a downloadable slip, summary or receipt, generated on the spot.'],
];
$apis = [
    ['ai', 'bg-ai', 'Gemini API', 'API 1', 'Google\'s AI model. Powers the AI Assistant page: plain-language questions and record summaries.', 'ai.google.dev'],
    ['mail', 'bg-mail', 'EmailJS API', 'API 2', 'Sends emails straight from the browser, no mail server. Powers the Email Alerts page.', 'emailjs.com'],
    ['pdf', 'bg-pdf', 'PDFShift API', 'API 3', 'Converts a record page into a PDF file. Powers the PDF Reports page.', 'pdfshift.io'],
];
$steps = [
    ['Sign up or log in', 'Staff, students and parents each get their own view.'],
    ['Open the dashboard', 'See today\'s summary and jump to any of the three tools.'],
    ['Work with records', 'Add a grade or attendance entry. Emails go out on their own.'],
    ['Ask, notify, export', 'Ask the AI, check email alerts, or download a PDF report.'],
];
$navLinks = [['#features', 'Features'], ['#apis', 'APIs'], ['#how', 'How it works'], ['#team', 'Team']];
?>
<div class="flex min-h-screen flex-col bg-page">
    <header class="relative z-20 border-b border-line bg-white">
        <div class="mx-auto flex h-14 max-w-[1440px] items-center justify-between px-4 md:h-[72px] md:px-10 xl:px-20">
            <a href="<?= e(url('/')) ?>" aria-label="CampusIQ home">
                <span class="flex items-center gap-2.5">
                    <span class="flex size-7 items-center justify-center rounded-[7px] bg-primary font-mono text-xs font-semibold text-white md:size-[34px] md:rounded-[9px] md:text-sm">CQ</span>
                    <span class="font-mono text-[15px] font-semibold md:text-lg">CampusIQ</span>
                </span>
            </a>
            <nav class="hidden gap-8 lg:flex" aria-label="Sections">
                <?php foreach ($navLinks as [$href, $label]): ?>
                    <a href="<?= $href ?>" class="text-sm text-body hover:text-primary"><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="hidden gap-2.5 md:flex">
                <?php if ($user): ?>
                    <a href="<?= e(url(Auth::home($user))) ?>" class="btn btn-primary">Open <?= $user['role'] === 'staff' ? 'dashboard' : 'my records' ?></a>
                <?php else: ?>
                    <a href="<?= e(url('/login')) ?>" class="btn btn-outline">Log in</a>
                    <a href="<?= e(url('/signup')) ?>" class="btn btn-primary">Sign up</a>
                <?php endif; ?>
            </div>
            <button type="button" class="flex size-11 items-center justify-center rounded-lg md:hidden" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu" data-menu-toggle>
                <?= icon('menu', 'size-[22px]', 1.8) ?>
            </button>
        </div>
        <div id="mobile-menu" class="absolute inset-x-0 top-full border-b border-line bg-white px-4 pt-2 pb-4 shadow-lg md:hidden" hidden>
            <nav class="flex flex-col" aria-label="Sections">
                <?php foreach ($navLinks as [$href, $label]): ?>
                    <a href="<?= $href ?>" class="border-b border-line-soft py-3 text-sm text-body" data-menu-close><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-3 grid grid-cols-2 gap-2.5">
                <?php if ($user): ?>
                    <a href="<?= e(url(Auth::home($user))) ?>" class="btn btn-primary col-span-2">Open <?= $user['role'] === 'staff' ? 'dashboard' : 'my records' ?></a>
                <?php else: ?>
                    <a href="<?= e(url('/login')) ?>" class="btn btn-outline">Log in</a>
                    <a href="<?= e(url('/signup')) ?>" class="btn btn-primary">Sign up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <!-- Hero -->
        <section class="dots bg-hero text-white">
            <div class="mx-auto flex max-w-[1440px] items-center gap-14 px-5 py-6 md:px-10 md:py-16 xl:min-h-[580px] xl:px-20">
                <div class="flex max-w-[560px] flex-col gap-3.5 md:gap-6">
                    <div class="eyebrow text-[10.5px] text-[#c7c9ff] md:text-xs">AI-Augmented Academic Records <span class="hidden md:inline">&amp; Integration System</span></div>
                    <h1 class="text-[26px] leading-[1.15] font-semibold tracking-[-0.02em] md:text-5xl md:leading-[1.12]">Academic records, without the digging.</h1>
                    <p class="max-w-[500px] text-sm leading-relaxed text-white/80 md:text-[17px]">
                        <span class="hidden md:inline">One place for grades, attendance and library records. </span>Ask questions in plain language, send updates automatically, and turn any record into a PDF.
                    </p>
                    <div class="flex flex-col gap-2.5 pt-1 sm:flex-row sm:gap-3">
                        <a href="<?= e(url($user ? Auth::home($user) : '/signup')) ?>" class="inline-flex h-12 items-center justify-center gap-2 rounded-[10px] bg-white px-[26px] text-[15px] font-semibold text-hero hover:bg-[#eef0ff] md:h-[50px]">
                            Get started<?= icon('arrow-right', 'size-4', 1.8) ?>
                        </a>
                        <?php if (!$user): ?>
                            <a href="<?= e(url('/login')) ?>" class="inline-flex h-12 items-center justify-center rounded-[10px] border-[1.4px] border-white/50 px-6 text-[15px] font-medium text-white hover:bg-white/10 md:h-[50px]">Log in</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Illustration: records table, AI answer, email toast, PDF page -->
                <div class="relative hidden h-[440px] w-[660px] shrink-0 xl:block" role="img" aria-label="Illustration of CampusIQ: a records table with an AI answer, an email notification and a PDF report around it">
                    <div class="absolute top-10 left-[70px] w-[460px] overflow-hidden rounded-[14px] bg-white text-ink shadow-[0_30px_70px_rgba(10,8,50,.45)]">
                        <div class="flex h-9 items-center gap-1.5 border-b border-line bg-page px-3.5">
                            <span class="size-[9px] rounded-full bg-[#d3d7e2]"></span><span class="size-[9px] rounded-full bg-[#d3d7e2]"></span><span class="size-[9px] rounded-full bg-[#d3d7e2]"></span>
                        </div>
                        <div class="flex flex-col gap-3 px-5 py-[18px]">
                            <div class="font-mono text-sm font-semibold">Grade 10-A records</div>
                            <div class="grid grid-cols-3 gap-2.5">
                                <?php foreach ([['Present today', '35/38'], ['Emails sent', '47'], ['PDFs made', '9']] as [$label, $value]): ?>
                                    <div class="rounded-lg border border-line p-2.5"><div class="text-[10px] text-muted"><?= $label ?></div><div class="font-mono text-lg font-semibold"><?= $value ?></div></div>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex flex-col text-[11.5px]">
                                <?php foreach ([['Grade', 'text-primary', 'Juan · Q1 Math 95', 'Sept 20'], ['Attendance', 'text-mail', 'Maria · Absent', 'Sept 25'], ['Library', 'text-lib', 'Ana · Borrowed', 'Sept 24'], ['Grade', 'text-primary', 'Paolo · Q1 Sci 88', 'Sept 23']] as [$type, $color, $text, $date]): ?>
                                    <div class="grid grid-cols-[90px_1fr_70px] border-b border-line-soft py-2 last:border-b-0"><span class="font-medium <?= $color ?>"><?= $type ?></span><span><?= $text ?></span><span class="text-faint"><?= $date ?></span></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="absolute top-0 left-0 flex w-[280px] gap-2.5 rounded-xl bg-white px-3.5 py-3 text-ink shadow-[0_16px_40px_rgba(10,8,50,.35)]">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><?= icon('ai', 'size-3.5') ?></span>
                        <div class="text-[11.5px] leading-normal"><span class="font-semibold">AI Assistant:</span> 3 students were late more than twice this week.</div>
                    </div>
                    <div class="absolute top-[330px] left-5 flex w-[300px] items-center gap-2.5 rounded-xl bg-side px-3.5 py-3 text-white shadow-[0_16px_40px_rgba(10,8,50,.4)]">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-mail"><?= icon('check', 'size-[13px]', 2.4) ?></span>
                        <div class="text-[11.5px] leading-snug"><div class="font-semibold">Email sent to Juan &amp; guardian</div><div class="text-[#9ea3bd]">New grade posted · just now</div></div>
                    </div>
                    <div class="absolute top-[150px] left-[500px] flex h-[210px] w-[150px] flex-col gap-1.5 rounded bg-white px-[11px] py-3 text-ink shadow-[0_20px_50px_rgba(10,8,50,.4)]">
                        <div class="font-mono text-[8px] font-semibold text-primary">CampusIQ</div>
                        <div class="h-0.5 bg-primary"></div>
                        <div class="text-[9px] font-semibold">Student Record</div>
                        <?php foreach (['w-4/5', 'w-[90%]', 'w-[70%]', 'w-[85%]'] as $w): ?><div class="h-[5px] rounded-[3px] bg-[#eceef4] <?= $w ?>"></div><?php endforeach; ?>
                        <div class="grow"></div>
                        <div class="self-start rounded-[3px] bg-pdf px-1.5 py-0.5 text-[8px] font-semibold text-white">PDF</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-[1440px]">
            <!-- Features -->
            <section id="features" class="flex scroll-mt-4 flex-col gap-6 px-4 pt-8 pb-4 md:gap-8 md:px-10 md:pt-16 md:pb-10 xl:px-20 xl:pt-[72px]">
                <div class="flex flex-col gap-2.5">
                    <div class="eyebrow text-primary">Features</div>
                    <h2 class="text-[26px] font-semibold tracking-[-0.01em] md:text-[30px]">What CampusIQ does</h2>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <?php foreach ($features as [$iconName, $iconClass, $name, $text]): ?>
                        <div class="flex flex-col gap-3 rounded-[14px] border border-line bg-white p-6">
                            <span class="flex size-11 items-center justify-center rounded-[11px] <?= $iconClass ?>"><?= icon($iconName, 'size-[22px]', 1.5) ?></span>
                            <div class="text-base font-semibold"><?= e($name) ?></div>
                            <p class="text-[13.5px] leading-relaxed text-[#5b6072]"><?= e($text) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- APIs -->
            <section id="apis" class="flex scroll-mt-4 flex-col gap-4 px-4 py-5 md:gap-8 md:px-10 md:py-10 xl:px-20">
                <div class="flex flex-col gap-2.5">
                    <div class="eyebrow text-[11px] text-primary md:text-xs">APIs used</div>
                    <h2 class="text-[26px] font-semibold tracking-[-0.01em] md:text-[30px]">3 APIs</h2>
                </div>
                <div class="grid gap-2.5 md:gap-5 lg:grid-cols-3">
                    <?php foreach ($apis as [$iconName, $bg, $name, $tag, $text, $site]): ?>
                        <div class="flex items-center gap-3 rounded-xl border border-line bg-white p-3 md:items-start md:gap-[18px] md:rounded-[14px] md:p-6">
                            <span class="flex size-[38px] shrink-0 items-center justify-center rounded-[10px] text-white md:size-11 md:rounded-[11px] <?= $bg ?>"><?= icon($iconName, 'size-5 md:size-[22px]', 1.5) ?></span>
                            <div class="flex flex-col gap-0.5 md:gap-2">
                                <div class="flex items-center gap-2.5"><div class="text-sm font-semibold md:text-base"><?= e($name) ?></div><span class="api-tag"><?= e($tag) ?></span></div>
                                <p class="text-xs text-muted md:text-[13.5px] md:leading-relaxed md:text-[#5b6072]"><?= e($text) ?></p>
                                <div class="hidden text-xs text-faint md:block"><?= e($site) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- How it works -->
            <section id="how" class="flex scroll-mt-4 flex-col gap-6 px-4 pt-6 pb-10 md:gap-7 md:px-10 md:pt-10 md:pb-[72px] xl:px-20">
                <div class="flex flex-col gap-2.5">
                    <div class="eyebrow text-primary">How it works</div>
                    <h2 class="text-[26px] font-semibold tracking-[-0.01em] md:text-[30px]">From landing page to results</h2>
                </div>
                <ol class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    <?php foreach ($steps as $i => [$name, $text]): ?>
                        <li class="flex flex-col items-start gap-2">
                            <span class="flex size-8 items-center justify-center rounded-full bg-primary font-mono text-[13px] font-semibold text-white"><?= $i + 1 ?></span>
                            <div class="text-base font-semibold"><?= e($name) ?></div>
                            <p class="text-[13.5px] leading-relaxed text-[#5b6072]"><?= e($text) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>
    </main>

    <footer id="team" class="mt-auto bg-side text-white">
        <!-- Mobile footer (design-ref 14) -->
        <div class="px-4 py-[18px] text-xs leading-[1.7] text-[#d7d9e6] md:hidden">
            <div class="font-semibold text-white">CommIT Team · BSIT 4A</div>
            <div>[Member 1], [Member 2], [Member 3], [Member 4]</div>
            <div class="text-[#8f94ad]">&copy; 2026 CommIT Team · [team email]</div>
        </div>
        <!-- Desktop footer -->
        <div class="mx-auto hidden max-w-[1440px] flex-col gap-10 px-10 pt-12 pb-8 md:flex xl:px-20">
            <div class="grid grid-cols-2 gap-10 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div class="flex flex-col gap-3">
                    <?= partial('logo', ['variant' => 'dark']) ?>
                    <p class="max-w-[300px] text-[13.5px] leading-[1.8] text-[#d7d9e6]">AI-Augmented Academic Records and Integration System. Systems Integration and Architecture 2 project.</p>
                </div>
                <div class="flex flex-col gap-2.5"><div class="text-xs font-semibold tracking-[.07em] text-[#8f94ad] uppercase">Team · CommIT</div><div class="text-[13.5px] leading-[1.8] text-[#d7d9e6]">[Member 1]<br>[Member 2]<br>[Member 3]<br>[Member 4]</div></div>
                <div class="flex flex-col gap-2.5"><div class="text-xs font-semibold tracking-[.07em] text-[#8f94ad] uppercase">School</div><div class="text-[13.5px] leading-[1.8] text-[#d7d9e6]">BSIT 4A<br>Holy Cross College<br>Sta. Ana, Pampanga</div></div>
                <div class="flex flex-col gap-2.5"><div class="text-xs font-semibold tracking-[.07em] text-[#8f94ad] uppercase">Contact</div><div class="text-[13.5px] leading-[1.8] text-[#d7d9e6]">[team email]<br>Instructor: [name]</div></div>
            </div>
            <div class="border-t border-white/10 pt-[18px] text-[12.5px] text-[#8f94ad]">&copy; 2026 CommIT Team. For academic use.</div>
        </div>
    </footer>
</div>
