<?php
// Online help & FAQ (SRS 2.6 User Documentation).
require __DIR__ . '/includes/functions.php';

$sections = [
    'help_tourists' => ['faq_t1', 'faq_t2', 'faq_t3', 'faq_t4', 'faq_t5', 'faq_t6'],
    'help_organizers' => ['faq_o1', 'faq_o2', 'faq_o3', 'faq_o4'],
    'help_general' => ['faq_g1', 'faq_g2', 'faq_g3'],
];
page_header(t('help_faq'));
?>
<div class="card medium">
  <h1><?= e(t('help_faq')) ?></h1>
  <?php foreach ($sections as $title => $keys): ?>
    <h2 class="mt"><?= e(t($title)) ?></h2>
    <?php foreach ($keys as $k): ?>
      <details class="mail inbox-item">
        <summary><b><?= e(t($k . '_q')) ?></b></summary>
        <div class="mail-body"><?= e(t($k . '_a')) ?></div>
      </details>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <p class="mt"><?= e(t('help_more')) ?> <a href="about.php#contact"><?= e(t('contact_us')) ?></a></p>
</div>
<?php page_footer();
