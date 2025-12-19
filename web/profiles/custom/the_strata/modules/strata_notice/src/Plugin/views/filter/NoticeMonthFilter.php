<?php

namespace Drupal\strata_notice\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filter notices by month with count display.
 *
 * @ViewsFilter("notice_month_filter")
 */
class NoticeMonthFilter extends FilterPluginBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->database = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['value'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $options = $this->getMonthOptions();

    // Only add "- Any -" option for exposed filters.
    if ($form_state->get('exposed')) {
      $options = $options;
    }

    $form['value'] = [
      '#type' => 'select',
      '#title' => $this->t('Month'),
      '#options' => $options,
      '#default_value' => $this->value,
    ];
  }

  /**
   * Get month options with counts.
   *
   * @return array
   *   Array of month options keyed by YYYY-MM format.
   */
  protected function getMonthOptions(): array {
    $options = [];

    // Get the "Published" term ID.
    $published_tid = $this->getPublishedTermId();
    if (!$published_tid) {
      return $options;
    }

    // Query to get distinct months with counts for notice nodes.
    $query = $this->database->select('node_field_data', 'n');
    $query->join('node__field_board_status', 'bs', 'n.nid = bs.entity_id AND bs.deleted = 0');
    $query->addExpression("DATE_FORMAT(FROM_UNIXTIME(n.created), '%Y-%m')", 'month_key');
    $query->addExpression("DATE_FORMAT(FROM_UNIXTIME(n.created), '%M %Y')", 'month_label');
    $query->addExpression('COUNT(*)', 'count');
    $query->condition('n.type', 'notice');
    $query->condition('n.status', 1);
    $query->condition('bs.field_board_status_target_id', $published_tid);
    $query->groupBy('month_key');
    $query->groupBy('month_label');
    $query->orderBy('month_key', 'DESC');

    $results = $query->execute()->fetchAll();

    foreach ($results as $row) {
      $options[$row->month_key] = $row->month_label . ' (' . $row->count . ')';
    }

    return $options;
  }

  /**
   * Get the term ID for "Published" status.
   *
   * @return int|null
   *   The term ID or NULL if not found.
   */
  protected function getPublishedTermId(): ?int {
    $result = $this->database->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid'])
      ->condition('t.vid', 'board_column')
      ->condition('t.name', 'Published')
      ->execute()
      ->fetchField();

    return $result ? (int) $result : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Get the value - it might be an array from the form.
    $value = $this->value;
    if (is_array($value)) {
      $value = reset($value);
    }

    if (empty($value)) {
      return;
    }

    $this->ensureMyTable();

    // Parse the YYYY-MM value.
    $parts = explode('-', $value);
    if (count($parts) !== 2) {
      return;
    }

    $year = (int) $parts[0];
    $month = (int) $parts[1];

    // Calculate start and end timestamps for the month.
    $start = mktime(0, 0, 0, $month, 1, $year);
    $end = mktime(23, 59, 59, $month + 1, 0, $year);

    $field = "$this->tableAlias.$this->realField";
    $this->query->addWhere($this->options['group'], $field, $start, '>=');
    $this->query->addWhere($this->options['group'], $field, $end, '<=');
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    $value = $this->value;
    if (is_array($value)) {
      $value = reset($value);
    }

    if (empty($value)) {
      return $this->t('All');
    }
    $options = $this->getMonthOptions();
    return $options[$value] ?? $value;
  }

}
