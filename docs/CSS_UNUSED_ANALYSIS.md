# Análisis de Código CSS Muerto (No Usado)

**Fecha:** 2025-11-11 22:40:24

## Resumen

- Clases CSS definidas: 581
- Clases usadas en HTML/JS: 301
- Clases potencialmente no usadas: 295
- Clases dinámicas (probablemente usadas): 34
- Posibles falsos positivos (elementos BEM): 78

## ⚠️ Advertencia Importante

**ANTES de eliminar cualquier clase CSS, DEBE verificarse manualmente:**

1. ✅ Probar todas las páginas que usan el CSS
2. ✅ Verificar en diferentes navegadores (Chrome, Firefox, Safari)
3. ✅ Verificar en diferentes tamaños de pantalla (mobile, tablet, desktop)
4. ✅ Verificar estados interactivos (hover, focus, active)
5. ✅ Verificar componentes dinámicos (modales, dropdowns, tooltips)
6. ✅ Verificar clases generadas dinámicamente por JavaScript
7. ✅ Verificar clases de Bootstrap 5 (no eliminar)
8. ✅ Verificar clases de utilidades (u-*) (no eliminar)

## Clases Potencialmente No Usadas

**⚠️ VERIFICAR MANUALMENTE ANTES DE ELIMINAR**

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\01-settings\theme.css

Clases potencialmente no usadas (5):

- `.text-corporate`
- `.bg-corporate`
- `.btn-corporate`
- `.css`
- `.btn-lg`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\04-elements\_accessibility.css

Clases potencialmente no usadas (7):

- `.css`
- `.01ms`
- `.5`
- `.btn-success`
- `.btn-danger`
- `.btn-warning`
- `.sr-only`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\05-objects\_common.css

Clases potencialmente no usadas (1):

- `.css`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_microinteractions.css

Clases potencialmente no usadas (18):

- `.css`
- `.01ms`
- `.5s`
- `.6s`
- `.2s`
- `.card-modern--interactive`
- `.list-item-interactive`
- `.btn-modern--ripple`
- `.pulse`
- `.shake`
- `.fade-in`
- `.fade-out`
- `.skeleton--pulse`
- `.spinner-bounce`
- `.spinner-bounce__dot`
- `.4s`
- `.3s`
- `.skeleton`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_visual-hierarchy.css

Clases potencialmente no usadas (21):

- `.css`
- `.content-group`
- `.section-header`
- `.section-title`
- `.section-subtitle`
- `.section-subtitle--muted`
- `.section`
- `.section--large`
- `.section--small`
- `.title-primary`
- `.title-primary--gradient`
- `.highlight-primary`
- `.important-badge`
- `.card-hierarchy-1`
- `.card-hierarchy-2`
- `.card-hierarchy-3`
- `.card-hierarchy-4`
- `.list-hierarchy`
- `.list-hierarchy__item`
- `.list-hierarchy__item--primary`
- `.list-hierarchy__item--secondary`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\approvals.css

Clases potencialmente no usadas (1):

- `.css`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\calendar.css

Clases potencialmente no usadas (16):

- `.css`
- `.fc-event`
- `.fc-event-title`
- `.fc-event-time`
- `.fc-event-group`
- `.fc-daygrid-event`
- `.fc-timegrid-event`
- `.fc-event-planned`
- `.fc-event-assigned`
- `.fc-event-in_progress`
- `.fc-event-done`
- `.fc-event-canceled`
- `.fc-header-toolbar`
- `.fc-toolbar-chunk`
- `.fc-button`
- `.filter-card`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\group-shift-report.css

Clases potencialmente no usadas (1):

- `.css`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\work-order-daily-report.css

Clases potencialmente no usadas (2):

- `.css`
- `.work-order-daily-report`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\work-orders-calendar.css

Clases potencialmente no usadas (2):

- `.css`
- `.fc-event-subtitle`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\work-orders-edit.css

Clases potencialmente no usadas (5):

- `.css`
- `.task-item`
- `.material-item`
- `.checklist-item`
- `.attachment-item`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\work-orders.css

Clases potencialmente no usadas (3):

- `.css`
- `.work-order-card`
- `.btn-group`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\agency-links.css

Clases potencialmente no usadas (3):

- `.btn-lg`
- `.text-end`
- `.page-link`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\onboarding.css

Clases potencialmente no usadas (7):

- `.btn-lg`
- `.form-check-lg`
- `.form-control-lg`
- `.form-select-lg`
- `.progress`
- `.progress-onboarding`
- `.progress-bar`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\settings.css

Clases potencialmente no usadas (2):

- `.btn-lg`
- `.form-check-switch`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\07-utilities\_opacity.css

Clases potencialmente no usadas (3):

- `.5`
- `.25`
- `.75`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\04-elements\_images.css

Clases potencialmente no usadas (22):

- `.responsive-image`
- `.responsive-image--square`
- `.responsive-image--portrait`
- `.responsive-image--landscape`
- `.loaded`
- `.object-fit-cover`
- `.object-fit-contain`
- `.object-fit-fill`
- `.object-fit-none`
- `.object-fit-scale-down`
- `.aspect-ratio-16-9`
- `.aspect-ratio-4-3`
- `.aspect-ratio-1-1`
- `.aspect-ratio-3-4`
- `.bg-image-responsive`
- `.avatar`
- `.avatar--sm`
- `.avatar--lg`
- `.thumbnail`
- `.js`
- `.image-loading`
- `.5s`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_skeleton.css

Clases potencialmente no usadas (25):

- `.5s`
- `.skeleton`
- `.skeleton--text`
- `.skeleton--title`
- `.skeleton--subtitle`
- `.skeleton--avatar`
- `.skeleton--image`
- `.skeleton--button`
- `.skeleton--card`
- `.skeleton--table-row`
- `.skeleton-grid`
- `.skeleton-grid--2`
- `.skeleton-grid--3`
- `.skeleton-grid--4`
- `.skeleton-card`
- `.skeleton-card__header`
- `.skeleton-card__avatar`
- `.skeleton-card__title`
- `.skeleton-card__body`
- `.skeleton-card__footer`
- `.skeleton-table`
- `.skeleton-table__row`
- `.skeleton-table__cell`
- `.skeleton-table__cell--short`
- `.skeleton-table__cell--long`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\04-elements\_tables.css

Clases potencialmente no usadas (16):

- `.table-modern-wrapper`
- `.table-modern--striped`
- `.modern-table`
- `.modern-table-wrapper`
- `.table-avatar`
- `.table-badge`
- `.inactive`
- `.pending`
- `.table-actions`
- `.action-btn`
- `.action-btn-view`
- `.action-btn-edit`
- `.action-btn-delete`
- `.modern-pagination`
- `.pagination-info`
- `.pagination-controls`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\04-elements\_typography.css

Clases potencialmente no usadas (1):

- `.125rem`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\05-objects\_dashboard-grid.css

Clases potencialmente no usadas (9):

- `.dashboard-grid--cards`
- `.dashboard-layout`
- `.dashboard-layout__sidebar`
- `.dashboard-layout__header`
- `.dashboard-layout__main`
- `.dashboard-grid--metrics-charts`
- `.dashboard-grid--welcome-actions`
- `.dashboard-grid--auto-fit`
- `.dashboard-grid--auto-fill`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\05-objects\_layout.css

Clases potencialmente no usadas (4):

- `.app-layout`
- `.app-header--scrolled`
- `.app-content`
- `.app-content-inner`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_achievements.css

Clases potencialmente no usadas (8):

- `.achievement-progress`
- `.achievement-progress__header`
- `.achievement-progress__title`
- `.achievement-progress__value`
- `.achievement-progress__bar`
- `.achievement-progress__milestones`
- `.achievement-progress__milestone`
- `.achievement-progress__milestone--completed`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_autocomplete.css

Clases potencialmente no usadas (1):

- `.autocomplete-item`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_badges.css

Clases potencialmente no usadas (11):

- `.badge-modern--danger`
- `.badge-modern--info`
- `.badge-modern--primary-solid`
- `.badge-modern--success-solid`
- `.badge-modern--warning-solid`
- `.badge-modern--danger-solid`
- `.badge-modern--info-solid`
- `.badge-modern--sm`
- `.badge-modern--md`
- `.badge-modern--lg`
- `.badge-modern--clickable`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_button-states.css

Clases potencialmente no usadas (3):

- `.spinner-border-sm`
- `.btn-success-icon`
- `.btn-error-icon`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_buttons.css

Clases potencialmente no usadas (3):

- `.6s`
- `.btn-modern--warning`
- `.btn-modern--info`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_candidate-item.css

Clases potencialmente no usadas (1):

- `.candidate-item`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\group-builder.css

Clases potencialmente no usadas (6):

- `.candidate-item`
- `.member-item`
- `.dragging`
- `.drag-handle`
- `.sortable-ghost`
- `.sortable-drag`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_cards.css

Clases potencialmente no usadas (4):

- `.card-modern--outlined`
- `.card-modern--gradient`
- `.card-modern--neumorphism`
- `.kpi-card--danger`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_charts.css

Clases potencialmente no usadas (36):

- `.chart`
- `.chart-container`
- `.chart-container--responsive`
- `.chart-tooltip`
- `.chart-tooltip__title`
- `.chart-tooltip__label`
- `.chart-tooltip__value`
- `.chart-tooltip__item`
- `.chart-tooltip__color`
- `.chart-legend`
- `.chart-legend__item`
- `.chart-legend__item--hidden`
- `.chart-legend__color`
- `.chart-legend__label`
- `.chart-legend__value`
- `.chart-legend--vertical`
- `.chart-legend--horizontal`
- `.chart-header`
- `.chart-title`
- `.chart-description`
- `.chart-grid`
- `.chart-axis`
- `.chart-axis-label`
- `.chart-axis-title`
- `.chart--bar`
- `.chart-bar`
- `.chart--line`
- `.chart-line`
- `.chart-point`
- `.chart--area`
- `.chart-area`
- `.chart--pie`
- `.chart-segment`
- `.chart-annotation`
- `.chart-indicator`
- `.8s`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_loading-spinner.css

Clases potencialmente no usadas (4):

- `.8s`
- `.loading-spinner--sm`
- `.loading-spinner--lg`
- `.relative-container`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_content-grouping.css

Clases potencialmente no usadas (9):

- `.content-group`
- `.content-group__header`
- `.content-group__title`
- `.content-group__subtitle`
- `.content-group__description`
- `.content-group__body`
- `.content-group--grid`
- `.content-group--list`
- `.content-group__item`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_data-table.css

Clases potencialmente no usadas (1):

- `.data-table--striped`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_empty-state.css

Clases potencialmente no usadas (2):

- `.empty-state--warning`
- `.empty-state--info`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_filters-sticky.css

Clases potencialmente no usadas (7):

- `.filters-sticky`
- `.filters-sticky--scrolled`
- `.filters-sticky__container`
- `.filters-sticky__toggle`
- `.filters-sticky__toggle-icon`
- `.filters-sticky__content`
- `.filters-sticky__content--expanded`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css

Clases potencialmente no usadas (1):

- `.rating-display`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_icons.css

Clases potencialmente no usadas (4):

- `.icon-xl`
- `.icon--warning`
- `.icon--danger`
- `.icon--spin`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_toast.css

Clases potencialmente no usadas (14):

- `.3s`
- `.toast`
- `.toast__content`
- `.toast__icon`
- `.toast__message`
- `.toast__close`
- `.toast--warning`
- `.toast--info`
- `.toast--constructive`
- `.toast__header`
- `.toast__title`
- `.toast__body`
- `.toast__description`
- `.toast__solution`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_modal.css

Clases potencialmente no usadas (1):

- `.modal-backdrop`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_modals.css

Clases potencialmente no usadas (1):

- `.2rem`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_progress-bar.css

Clases potencialmente no usadas (19):

- `.progress-bar-container`
- `.progress-bar-container--sm`
- `.progress-bar-container--md`
- `.progress-bar-container--lg`
- `.progress-bar-modern`
- `.progress-bar-modern--sm`
- `.progress-bar-modern--lg`
- `.progress-bar-modern__bar`
- `.progress-bar-modern__bar--warning`
- `.progress-bar-modern__bar--danger`
- `.progress-bar-modern__bar--info`
- `.progress-bar-modern__percentage`
- `.progress-bar-modern__steps`
- `.progress-bar-modern__step`
- `.progress-bar-modern__step--current`
- `.progress-bar-modern__step-number`
- `.progress-bar-modern__labels`
- `.progress-bar-modern__label`
- `.progress-bar-modern__bar--animated`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_tooltip.css

Clases potencialmente no usadas (8):

- `.tooltip-modern`
- `.tooltip-modern__arrow`
- `.tooltip-modern--top`
- `.tooltip-modern--bottom`
- `.tooltip-modern--left`
- `.tooltip-modern--right`
- `.tooltip-modern__content`
- `.tooltip-modern__content--multiline`

### C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\assignments.css

Clases potencialmente no usadas (1):

- `.assignment-card`

## Clases Dinámicas (Probablemente Usadas por JavaScript)

Estas clases probablemente se agregan/remueven dinámicamente por JavaScript:

- `.sidebar-nav__link--active` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\05-objects\_layout.css)
- `.app-sidebar--open` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\05-objects\_layout.css)
- `.sidebar-overlay--show` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\05-objects\_layout.css)
- `.autocomplete-dropdown--show` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_autocomplete.css)
- `.autocomplete-item--selected` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_autocomplete.css)
- `.btn--loading` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_button-states.css)
- `.btn--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_button-states.css)
- `.btn--error` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_button-states.css)
- `.btn-modern--loading` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_buttons.css)
- `.chart-tooltip--show` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_charts.css)
- `.chart-container--loading` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_charts.css)
- `.data-table__row--active` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_data-table.css)
- `.empty-state--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_empty-state.css)
- `.form-modern__feedback--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css)
- `.form-modern__input--error` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css)
- `.form-modern__select--error` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css)
- `.form-modern__textarea--error` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css)
- `.form-modern__input--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css)
- `.form-modern__select--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css)
- `.form-modern__textarea--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_forms-modern.css)
- `.global-search-modal--show` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_global-search.css)
- `.global-search-modal__result--selected` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_global-search.css)
- `.icon--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_icons.css)
- `.loading-spinner--show` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_loading-spinner.css)
- `.loading-spinner--hide` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_loading-spinner.css)
- `.toast--show` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_microinteractions.css, C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_toast.css)
- `.toast--hide` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_microinteractions.css, C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_toast.css)
- `.progress-bar-modern__bar--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_progress-bar.css)
- `.progress-bar-modern__step--active` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_progress-bar.css)
- `.progress-bar-modern__label--active` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_progress-bar.css)
- `.toast--success` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_toast.css)
- `.toast--error` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_toast.css)
- `.tooltip-modern--show` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\06-components\_tooltip.css)
- `.loading` (en: C:\Program Files\xammp\htdocs\SaSS_Workee\scripts\dev/../../frontend/css\pages\owner.css)

## Posibles Falsos Positivos (Elementos BEM)

Estas clases son elementos BEM que probablemente se usan aunque no aparezcan directamente en HTML:

- `.dashboard-card__body` (bloque: dashboard-card)
- `.dashboard-card__title` (bloque: dashboard-card)
- `.dashboard-card__text` (bloque: dashboard-card)
- `.sidebar-brand__text` (bloque: sidebar-brand)
- `.sidebar-brand__logo` (bloque: sidebar-brand)
- `.sidebar-nav__section` (bloque: sidebar-nav)
- `.sidebar-nav__section-title` (bloque: sidebar-nav)
- `.sidebar-nav__subsection` (bloque: sidebar-nav)
- `.sidebar-nav__subsection-title` (bloque: sidebar-nav)
- `.sidebar-nav__item` (bloque: sidebar-nav)
- `.sidebar-nav__link` (bloque: sidebar-nav)
- `.sidebar-nav__link-icon` (bloque: sidebar-nav)
- `.sidebar-nav__link-text` (bloque: sidebar-nav)
- `.app-header__left` (bloque: app-header)
- `.app-header__right` (bloque: app-header)
- `.breadcrumb-nav__link` (bloque: breadcrumb-nav)
- `.breadcrumb-nav__separator` (bloque: breadcrumb-nav)
- `.breadcrumb-nav__item` (bloque: breadcrumb-nav)
- `.breadcrumb-nav__item--current` (bloque: breadcrumb-nav)
- `.header-search__input` (bloque: header-search)
- `.header-search__icon` (bloque: header-search)
- `.header-action-btn__badge` (bloque: header-action-btn)
- `.user-profile__avatar` (bloque: user-profile)
- `.user-profile__info` (bloque: user-profile)
- `.user-profile__name` (bloque: user-profile)
- `.user-profile__role` (bloque: user-profile)
- `.page-header__title` (bloque: page-header)
- `.page-header__actions` (bloque: page-header)
- `.achievement-banner__content` (bloque: achievement-banner)
- `.achievement-banner__icon` (bloque: achievement-banner)
- `.achievement-banner__text` (bloque: achievement-banner)
- `.achievement-banner__title` (bloque: achievement-banner)
- `.achievement-banner__description` (bloque: achievement-banner)
- `.achievement-banner__close` (bloque: achievement-banner)
- `.achievement-badge__icon` (bloque: achievement-badge)
- `.achievement-badge__title` (bloque: achievement-badge)
- `.badge-modern__icon` (bloque: badge-modern)
- `.badge-modern__icon--left` (bloque: badge-modern)
- `.badge-modern__icon--right` (bloque: badge-modern)
- `.btn-modern__spinner` (bloque: btn-modern)
- `.card-modern__subtitle` (bloque: card-modern)
- `.card-modern__footer` (bloque: card-modern)
- `.card-modern__icon` (bloque: card-modern)
- `.kpi-card__trend--down` (bloque: kpi-card)
- `.kpi-card__trend--neutral` (bloque: kpi-card)
- `.kpi-card__description` (bloque: kpi-card)
- `.data-table__table` (bloque: data-table)
- `.data-table__header` (bloque: data-table)
- `.data-table__header--sortable` (bloque: data-table)
- `.data-table__row` (bloque: data-table)
- `.data-table__checkbox` (bloque: data-table)
- `.data-table__header--sort-asc` (bloque: data-table)
- `.data-table__header--sort-desc` (bloque: data-table)
- `.empty-state__content` (bloque: empty-state)
- `.empty-state__action` (bloque: empty-state)
- `.form-modern__label--floating` (bloque: form-modern)
- `.form-modern__feedback-icon` (bloque: form-modern)
- `.form-modern__input-group` (bloque: form-modern)
- `.form-modern__input-group-prepend` (bloque: form-modern)
- `.form-modern__input-group-append` (bloque: form-modern)
- `.global-search-modal__backdrop` (bloque: global-search-modal)
- `.global-search-modal__content` (bloque: global-search-modal)
- `.global-search-modal__header` (bloque: global-search-modal)
- `.global-search-modal__input` (bloque: global-search-modal)
- `.global-search-modal__close` (bloque: global-search-modal)
- `.global-search-modal__results` (bloque: global-search-modal)
- `.global-search-modal__result` (bloque: global-search-modal)
- `.global-search-modal__result-icon` (bloque: global-search-modal)
- `.global-search-modal__result-content` (bloque: global-search-modal)
- `.global-search-modal__result-title` (bloque: global-search-modal)
- `.global-search-modal__result-url` (bloque: global-search-modal)
- `.global-search-modal__empty` (bloque: global-search-modal)
- `.global-search-modal__footer` (bloque: global-search-modal)
- `.loading-spinner__content` (bloque: loading-spinner)
- `.loading-spinner__spinner` (bloque: loading-spinner)
- `.loading-spinner__message` (bloque: loading-spinner)
- `.page-header__subtitle` (bloque: page-header)
- `.page-header__description` (bloque: page-header)

## Recomendaciones

1. **Revisar manualmente** cada clase antes de eliminar
2. **Probar funcionalidad** después de cada eliminación
3. **Mantener clases de Bootstrap 5** y utilidades (u-*)
4. **No eliminar clases dinámicas** sin verificar su uso en JavaScript
5. **Considerar elementos BEM** como parte del bloque principal
6. **Eliminar incrementalmente** (archivo por archivo o sección por sección)
7. **Verificar después de cada eliminación** que no se rompió nada
