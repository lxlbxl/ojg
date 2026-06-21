<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// ── Nav groups ────────────────────────────────────────────────────────────────
$groups = [
    'primary' => [
        'dashboard.php'       => ['label' => 'Dashboard',    'icon' => 'fa-home'],
        'assessments.php'     => ['label' => 'Assessments',  'icon' => 'fa-clipboard-list'],
        'sales.php'           => ['label' => 'Sales',        'icon' => 'fa-shopping-cart'],
        'manage-users.php'    => ['label' => 'Members',      'icon' => 'fa-users'],
    ],
    'Analytics' => [
        'member-pulse.php'    => ['label' => '🏆 Member Pulse', 'icon' => 'fa-trophy'],
        'funnel-tracking.php' => ['label' => 'Funnels',         'icon' => 'fa-filter'],
        'experiments.php'     => ['label' => 'A/B Tests',       'icon' => 'fa-flask'],
        'reports.php'         => ['label' => 'Reports',          'icon' => 'fa-chart-bar'],
    ],
    'Operations' => [
        'pcos-plan.php'       => ['label' => '🌿 PCOS Plan',  'icon' => 'fa-leaf'],
        'pricing.php'         => ['label' => 'Pricing',        'icon' => 'fa-tag'],
        'webhooks.php'        => ['label' => 'Webhooks',       'icon' => 'fa-link'],
        'export.php'          => ['label' => 'Export Data',    'icon' => 'fa-download'],
    ],
    'System' => [
        'ai-oversight.php'    => ['label' => 'AI Oversight',  'icon' => 'fa-robot'],
        'audit-logs.php'      => ['label' => 'Audit Logs',    'icon' => 'fa-shield-alt'],
        'settings.php'        => ['label' => 'Settings',      'icon' => 'fa-cog'],
    ],
];

// Which dropdown group is the current page in?
$activeGroup = null;
foreach ($groups as $groupName => $items) {
    if (isset($items[$currentPage])) {
        $activeGroup = $groupName;
        break;
    }
}
?>

<nav class="bg-transparent" x-data="{
    mobileMenuOpen: false,
    openDropdown: null,
    toggleDropdown(name) { this.openDropdown = this.openDropdown === name ? null : name; }
}" @click.away="openDropdown = null">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">

            <!-- Logo + Desktop Nav -->
            <div class="flex items-center gap-1">
                <!-- Logo -->
                <a href="dashboard.php" class="flex items-center gap-2 mr-3 shrink-0">
                    <i class="fas fa-leaf text-[#D97757]"></i>
                    <span class="hidden sm:inline font-serif text-lg text-[#2C3E35]">OJG Admin</span>
                </a>

                <!-- Desktop primary links -->
                <div class="hidden md:flex items-center gap-1">
                    <?php foreach ($groups['primary'] as $file => $info):
                        $active = ($currentPage === $file);
                    ?>
                    <a href="<?php echo $file; ?>"
                       class="px-3 py-2 rounded-full text-sm font-medium transition-colors <?php echo $active ? 'bg-[#E3E8E1] text-[#2C3E35]' : 'text-[#6B7C70] hover:bg-[#F2F4F1] hover:text-[#2C3E35]'; ?>">
                        <?php echo $info['label']; ?>
                    </a>
                    <?php endforeach; ?>

                    <!-- Dropdown groups -->
                    <?php foreach (['Analytics', 'Operations', 'System'] as $groupName):
                        $isGroupActive = ($activeGroup === $groupName);
                    ?>
                    <div class="relative" @click.stop>
                        <button @click="toggleDropdown('<?php echo $groupName; ?>')"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-full text-sm font-medium transition-colors
                                       <?php echo $isGroupActive ? 'bg-[#E3E8E1] text-[#2C3E35]' : 'text-[#6B7C70] hover:bg-[#F2F4F1] hover:text-[#2C3E35]'; ?>">
                            <?php echo $groupName; ?>
                            <i class="fas fa-chevron-down text-[10px] transition-transform duration-200"
                               :class="openDropdown === '<?php echo $groupName; ?>' ? 'rotate-180' : ''"></i>
                        </button>

                        <div x-show="openDropdown === '<?php echo $groupName; ?>'"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                             style="display:none;"
                             class="absolute left-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-[#EAEAE5] py-1.5 z-50 origin-top-left">
                            <?php foreach ($groups[$groupName] as $file => $info):
                                $active = ($currentPage === $file);
                            ?>
                            <a href="<?php echo $file; ?>"
                               class="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors
                                      <?php echo $active ? 'bg-[#E3E8E1] text-[#2C3E35] font-medium' : 'text-[#6B7C70] hover:bg-[#F2F4F1] hover:text-[#2C3E35]'; ?>">
                                <i class="fas <?php echo $info['icon']; ?> w-4 text-center opacity-60"></i>
                                <?php echo $info['label']; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: User Menu (desktop) -->
            <div class="hidden md:flex items-center">
                <div class="relative" x-data="{ userMenuOpen: false }" @click.away="userMenuOpen = false">
                    <button @click="userMenuOpen = !userMenuOpen"
                            class="flex items-center gap-2 px-3 py-2 rounded-full text-sm font-medium text-[#6B7C70] hover:bg-[#F2F4F1] transition-colors">
                        <div class="h-7 w-7 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35]">
                            <i class="fas fa-user text-xs"></i>
                        </div>
                        <span class="font-serif"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                        <i class="fas fa-chevron-down text-[10px]" :class="userMenuOpen ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="userMenuOpen" style="display:none;"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-44 bg-white rounded-2xl shadow-xl border border-[#EAEAE5] py-1.5 z-50">
                        <a href="settings.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#6B7C70] hover:bg-[#F2F4F1] hover:text-[#2C3E35]">
                            <i class="fas fa-cog w-4 text-center opacity-60"></i> Settings
                        </a>
                        <div class="border-t border-[#EAEAE5] my-1"></div>
                        <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#D97757] hover:bg-[#FDF1E8]">
                            <i class="fas fa-sign-out-alt w-4 text-center"></i> Sign out
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile: hamburger -->
            <div class="flex items-center md:hidden">
                <button @click="mobileMenuOpen = !mobileMenuOpen"
                        class="p-2 rounded-lg text-[#6B7C70] hover:text-[#2C3E35] hover:bg-[#F2F4F1] transition-colors">
                    <i class="fas text-lg" :class="mobileMenuOpen ? 'fa-times' : 'fa-bars'"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div x-show="mobileMenuOpen" style="display:none;"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="md:hidden border-t border-[#EAEAE5] bg-white shadow-lg">

        <div class="px-4 py-3 space-y-0.5">
            <?php foreach ($groups as $groupName => $items):
                if ($groupName !== 'primary'): ?>
                    <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-[#A4B4A6] px-3 pt-4 pb-1"><?php echo $groupName; ?></p>
                <?php endif;
                foreach ($items as $file => $info):
                    $active = ($currentPage === $file);
                ?>
                <a href="<?php echo $file; ?>"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
                          <?php echo $active ? 'bg-[#E3E8E1] text-[#2C3E35]' : 'text-[#6B7C70] hover:bg-[#F2F4F1] hover:text-[#2C3E35]'; ?>">
                    <i class="fas <?php echo $info['icon']; ?> w-4 text-center opacity-60"></i>
                    <?php echo $info['label']; ?>
                </a>
                <?php endforeach;
            endforeach; ?>
        </div>

        <!-- Mobile user footer -->
        <div class="border-t border-[#EAEAE5] px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 rounded-full bg-[#E3E8E1] flex items-center justify-center text-[#2C3E35]">
                    <i class="fas fa-user text-sm"></i>
                </div>
                <div>
                    <p class="text-sm font-serif font-medium text-[#2C3E35]"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></p>
                    <p class="text-xs text-[#6B7C70]">Administrator</p>
                </div>
            </div>
            <a href="logout.php" class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium text-[#D97757] hover:bg-[#FDF1E8] transition-colors">
                <i class="fas fa-sign-out-alt"></i> Sign out
            </a>
        </div>
    </div>
</nav>
