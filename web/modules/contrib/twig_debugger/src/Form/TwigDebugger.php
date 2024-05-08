<?php

namespace Drupal\twig_debugger\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Twig Debugger.
 *
 * @package Drupal\twig_debugger\Form
 */
class TwigDebugger extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['twig_debugger.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'twig_debugger_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('twig_debugger.settings');
    $form['configuration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Twig Debugger Configuration'),
    ];
    $form['configuration']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Twig Debugging'),
      '#default_value' => $config->get('enabled'),
      '#description' => $this->t('If checked/ticked, twig debugging will be enable for the site.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('twig_debugger.settings')
      ->set('enabled', trim($form_state->getValue('enabled')))
      ->save();
    if ($form_state->getValue('enabled') === 1) {
      $destination = DRUPAL_ROOT . '/sites/default/services.yml';
      if (file_exists($destination) === FALSE) {
        $source = DRUPAL_ROOT . '/sites/default/default.services.yml';
        chmod(DRUPAL_ROOT . '/sites/default', 0777);
        touch($destination);
        copy($source, $destination);
        chmod(DRUPAL_ROOT . '/sites/default', 0555);
        file_put_contents($destination, str_replace('debug: false', 'debug: true', file_get_contents($destination)));
        file_put_contents($destination, str_replace('auto_reload: null', 'auto_reload: true', file_get_contents($destination)));
        file_put_contents($destination, str_replace('cache: true', 'cache: false', file_get_contents($destination)));
        drupal_flush_all_caches();
      }
    }
    else {
      $source = DRUPAL_ROOT . '/sites/default/services.yml';
      if (file_exists($source) === TRUE) {
        chmod(DRUPAL_ROOT . '/sites/default', 0777);
        unlink($source);
        chmod(DRUPAL_ROOT . '/sites/default', 0555);
        drupal_flush_all_caches();
      }
    }
    parent::submitForm($form, $form_state);
  }

}
