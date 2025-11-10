<?php

namespace Drupal\iq_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\iq_group\Form\UserEditForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'User edit' Block.
 *
 * @Block(
 *   id = "user_edit_block",
 *   admin_label = @Translation("User block"),
 *   category = @Translation("Forms"),
 * )
 */
class UserEditBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new UserEditBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The current route match.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user, CurrentRouteMatch $route_match, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = [];
    $user_id = $this->currentUser->id();
    $route_name = $this->routeMatch->getRouteName();
    $route_user = $this->routeMatch->getParameter('user');

    // Check if we're on the user profile page for the current user.
    if ($route_name === 'entity.user.canonical' && $route_user && $route_user->id() == $user_id) {
      $form['full_profile_edit'] = [
        '#type' => 'link',
        '#title' => $this->t('Edit profile'),
        '#url' => Url::fromRoute('entity.user.edit_form', ['user' => $user_id]),
      ];
      $form['full_profile_edit']['#attributes']['class'][] = 'iqbm-button iqbm-text btn btn-cta';
      return $form;
    }
    return $this->formBuilder->getForm(UserEditForm::class);
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
