<?php

namespace Drupal\the_strata_theme;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

include_once __DIR__ . '/../the_strata_theme.theme';
_the_strata_theme_include_theme_includes();

/**
 * Service to handle toggling form descriptions.
 */
class TheStrataThemeDescriptionToggle implements ContainerInjectionInterface {


  /**
   * The content form helper class.
   *
   * @var \Drupal\the_strata_theme\TheStrataThemeContentFormHelper
   */
  protected $contentFormHelper;

  /**
   * The the_strata_theme theme settings class.
   *
   * @var \Drupal\the_strata_theme\TheStrataThemeSettings
   */
  protected $theStrataThemeSettings;

  /**
   * TheStrataThemeDescriptionToggle constructor.
   *
   * @param \Drupal\the_strata_theme\TheStrataThemeSettings $theStrataThemeSettings
   *   The the_strata_theme theme settings class.
   * @param \Drupal\the_strata_theme\TheStrataThemeContentFormHelper $contentFormHelper
   *   The content form helper class.
   */
  public function __construct(TheStrataThemeSettings $theStrataThemeSettings, TheStrataThemeContentFormHelper $contentFormHelper) {
    $this->theStrataThemeSettings = $theStrataThemeSettings;
    $this->contentFormHelper = $contentFormHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $classResolver = $container->get('class_resolver');

    return new static(
      $classResolver->getInstanceFromDefinition(TheStrataThemeSettings::class),
      $classResolver->getInstanceFromDefinition(TheStrataThemeContentFormHelper::class),
    );
  }

  /**
   * Generic preprocess enabling toggle.
   *
   * @param array $variables
   *   The variables array (modify in place).
   */
  public function preprocess(array &$variables) {
    if ($this->isEnabled() || (isset($variables['element']['#description_toggle']) && $variables['element']['#description_toggle'])) {
      if (!empty($variables['description'])) {
        $variables['description_display'] = 'invisible';
        $variables['description_toggle'] = TRUE;
      }
      // Add toggle for text_format, description is in wrapper.
      elseif (!empty($variables['element']['#description_toggle'])) {
        $variables['description_toggle'] = TRUE;
      }
    }
  }

  /**
   * Functionality is enabled via setting on content forms.
   *
   * @return bool
   *   Wether feature is enabled or not.
   */
  public function isEnabled() {
    return $this->theStrataThemeSettings->get('show_description_toggle') && $this->contentFormHelper->isContentForm();
  }

}
