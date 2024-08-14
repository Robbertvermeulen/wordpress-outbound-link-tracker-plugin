<div class="wrap">

    <div class="mb-4">
        <h1>Tracked Links Analytics</h1>
    </div>

    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="mb-6 p-5 flex gap-2 bg-white border-solid border border-slate-500 rounded-md shadow-md">
        <div>
            <label class="block">Date from:</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        <div>
            <label class="block">Date to:</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        <div class="mt-auto">
            <button class="button-primary">Filter</button>
        </div>
        <input type="hidden" name="action" value="ucds_submit_analytics_data">
    </form>

    <div>

        <div class="mb-4">
            <h2 class="font-semibold"><?php echo 'Tracked Links' . ' from: ' . $date_from . ' to: ' . $date_to; ?></h2>
        </div>

        <div class="grid grid-cols-3 gap-5 justify-between">
            <?php 
            if (!empty($stats)) {
                foreach ($stats as $link) { ?>

                    <div class="w-full mb-3 p-5 bg-white border border-solid border-slate-400 rounded-md border-t-[#2271b1] border-t-4">
                        <div class="mb-3 pb-4 border-b">
                            <strong class="mb-2 text-xl block"><?php echo $link['link_name']; ?></strong>
                            <a href="<?php echo get_edit_post_link($link['link_id']) ?>" target="_blank" class="text-[#2271b1] hover:underline"><?php echo $link['link_url']; ?></a>
                        </div>
                        <div>
                            <div class="pb-3 border-b flex justify-between">
                                <div class="font-semibold text-lg mb-1">Total visit count:</div>
                                <div class="text-lg"><?php echo $link['visit_count'] . ' visits'; ?></div>
                            </div>
                            <?php if (!empty($link['system'])) { ?>
                                <div class="mt-3">
                                    <div class="font-semibold text-lg mb-3"><?php echo 'By tech:'; ?></div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <?php foreach ($link['system'] as $system) { ?>
                                            <div class="p-4 bg-slate-100 rounded-md">
                                                <div class="flex justify-between mb-2">
                                                    <div class="font-semibold">Browser:</div>
                                                    <div><?php echo ucfirst($system['browser']); ?></div>
                                                </div>
                                                <div class="flex justify-between mb-2">
                                                    <div class="font-semibold">OS:</div>
                                                    <div><?php echo ucfirst(str_replace('_', ' ', $system['os'])); ?></div>
                                                </div>
                                                <div class="flex justify-between">
                                                    <div class="font-semibold">Visit count:</div>
                                                    <div><?php echo $system['visit_count']; ?></div>
                                                </div>    
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>

                    <?php
                }
            } else {
                echo 'No data available.';
            }
            ?>
        </div>
    </div>
</div>