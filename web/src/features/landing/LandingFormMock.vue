<script setup lang="ts">
import { AnimatePresence, motion } from 'motion-v'
import { computed, nextTick, ref, type ComponentPublicInstance } from 'vue'

import AppButton from '@/components/AppButton.vue'
import AppIcon from '@/components/AppIcon.vue'
import { distance, duration, ease, fade } from '@/motion'
import { landing } from '@/strings'

/**
 * Formulário de exemplo do hero (tema Clássico, sempre claro) sobre camadas de
 * profundidade. Dá para responder de verdade: escolher uma opção e enviar pela
 * setinha mostra um agradecimento com o check se desenhando, uma resposta para
 * cada opção, e o selo de leads ganha mais um. Nada sai do navegador.
 */
const t = landing.mock

const choice = ref(0)
const sent = ref(false)
/** Começa em 12 (a referência) e soma a cada envio. */
const leads = ref(12)
/**
 * Para qual estado o foco deve ir: só depois de um clique da pessoa (nunca ao
 * carregar) e uma vez só. O Vue chama refs-função a cada atualização, inclusive
 * no estado que está saindo; por isso o destino é marcado por estado.
 */
let focusPending: 'form' | 'thanks' | null = null

const reply = computed(() => t.options[choice.value]?.reply ?? '')

function send(): void {
  focusPending = 'thanks'
  sent.value = true
  leads.value += 1
}

function again(): void {
  focusPending = 'form'
  sent.value = false
}

/** Ao trocar de estado, o foco vai para o controle novo (o anterior sumiu). */
function focusFor(state: 'form' | 'thanks') {
  return (target: Element | ComponentPublicInstance | null): void => {
    if (focusPending !== state || !target) return
    const element = target instanceof Element ? target : (target.$el as Element)
    if (element instanceof HTMLElement) {
      focusPending = null
      // A ref chega antes de o elemento estar na página.
      void nextTick(() => element.focus())
    }
  }
}
const focusForm = focusFor('form')
const focusThanks = focusFor('thanks')

const drawCheck = {
  initial: { pathLength: 0 },
  animate: {
    pathLength: 1,
    transition: { duration: duration.slow, ease: ease.out, delay: duration.base },
  },
}
const bump = {
  initial: { opacity: 0, y: distance.sm },
  animate: { opacity: 1, y: 0, transition: { duration: duration.base, ease: ease.out } },
}
</script>

<template>
  <div class="form-mock">
    <span class="form-mock__layer form-mock__layer--back js-mock-layer" aria-hidden="true" />
    <span class="form-mock__layer form-mock__layer--middle js-mock-layer" aria-hidden="true" />

    <section class="form-mock__card js-mock-card" :aria-label="t.label">
      <!-- Os dois estados ocupam a mesma célula: o cartão não muda de altura na troca. -->
      <AnimatePresence>
        <motion.form
          v-if="!sent"
          key="form"
          class="form-mock__state"
          v-bind="fade"
          @submit.prevent="send"
        >
          <fieldset class="form-mock__fieldset">
            <legend class="form-mock__question">{{ t.question }}</legend>
            <label
              v-for="(option, index) in t.options"
              :key="option.label"
              class="form-mock__option"
              :class="{ 'form-mock__option--checked': choice === index }"
            >
              <input
                :ref="index === choice ? focusForm : undefined"
                v-model="choice"
                type="radio"
                name="landing-mock"
                :value="index"
                class="form-mock__input"
              />
              <span class="form-mock__radio" aria-hidden="true" />
              {{ option.label }}
            </label>
          </fieldset>
          <button type="submit" class="form-mock__next" :aria-label="t.send">
            <AppIcon name="arrow-right" />
          </button>
        </motion.form>

        <motion.div v-else key="thanks" class="form-mock__state form-mock__thanks" v-bind="fade">
          <svg class="form-mock__check" viewBox="0 0 48 48" aria-hidden="true">
            <circle cx="24" cy="24" r="22" class="form-mock__check-ring" />
            <motion.path
              d="M15 24.5l6.5 6.5L33 18"
              class="form-mock__check-mark"
              v-bind="drawCheck"
            />
          </svg>
          <p class="form-mock__thanks-title" role="status">
            {{ t.thanks }}
            <span class="form-mock__thanks-text">{{ reply }}</span>
          </p>
          <AppButton :ref="focusThanks" variant="secondary" size="sm" @click="again">
            {{ t.again }}
          </AppButton>
        </motion.div>
      </AnimatePresence>
    </section>

    <div class="form-mock__leads js-mock-leads" aria-hidden="true">
      <span class="form-mock__leads-value">
        <motion.span :key="leads" class="form-mock__leads-number" v-bind="bump">
          +{{ leads }}
        </motion.span>
      </span>
      <span class="form-mock__leads-text">
        {{ t.leads }}
        <span class="form-mock__leads-period">{{ sent ? t.leadsYou : t.leadsPeriod }}</span>
      </span>
      <AppIcon name="trending-up" class="form-mock__leads-icon" />
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/styles/themes/dark';
@use '@/styles/themes/light';

.form-mock {
  position: relative;
  display: grid;
  inline-size: 100%;
  max-inline-size: var(--landing-mock-width);
  padding-block: var(--space-8);

  // Camadas atrás do cartão: superfícies sólidas levemente giradas (sem vidro nem brilho).
  // Só no desktop, junto com a inclinação do cartão (no celular vazariam pela borda).
  &__layer {
    position: absolute;
    display: none;
    inset: var(--space-8) 0;
    background: var(--color-surface-raised);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-xl);

    @include up(lg) {
      display: block;
    }

    &--back {
      transform: translate(calc(var(--space-12) * -1), calc(var(--space-4) * -1)) rotate(-10deg);
    }

    &--middle {
      background: var(--color-surface);
      transform: translate(calc(var(--space-6) * -1), calc(var(--space-2) * -1)) rotate(-6deg);
    }
  }

  // O formulário em si: tema Clássico (claro) nos dois temas do app.
  &__card {
    @include light.values;
    @include color-roles;

    position: relative;
    display: grid;
    // Mesma altura nos dois estados: o agradecimento não encolhe o cartão.
    min-block-size: var(--landing-mock-height);
    padding: var(--space-6);
    color: var(--color-text);
    background: var(--color-surface);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-lg);

    // Inclinado só no desktop; no celular, reto e fácil de ler.
    @include up(lg) {
      transform: rotate(var(--landing-mock-tilt));
    }
  }

  // Formulário e agradecimento na mesma célula do grid (um sai enquanto o outro entra).
  &__state {
    display: grid;
    grid-area: 1 / 1;
    gap: var(--space-4);
  }

  &__fieldset {
    display: grid;
    gap: var(--space-3);
    min-inline-size: 0;
    padding: 0;
    margin: 0;
    border: 0;
  }

  &__question {
    padding: 0;
    margin-block-end: var(--space-4);
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
    line-height: var(--line-height-tight);
  }

  &__option {
    display: flex;
    gap: var(--space-2);
    align-items: center;
    font-size: var(--font-size-sm);
    color: var(--color-text-muted);
    cursor: pointer;
    transition: color var(--duration-fast) var(--ease-out);

    &:hover,
    &--checked {
      color: var(--color-text);
    }
  }

  &__input {
    @include visually-hidden;
  }

  &__radio {
    flex: none;
    inline-size: var(--space-4);
    block-size: var(--space-4);
    border: var(--border-width) solid var(--color-border-control);
    border-radius: var(--radius-full);
    transition: border var(--duration-fast) var(--ease-out);

    .form-mock__option--checked & {
      border: var(--space-1) solid var(--color-primary);
    }

    .form-mock__input:focus-visible + & {
      @include focus-ring;
    }
  }

  &__next {
    display: grid;
    place-items: center;
    justify-self: end;
    inline-size: var(--space-8);
    block-size: var(--space-8);
    padding: 0;
    color: var(--color-primary-contrast);
    cursor: pointer;
    background: var(--color-primary);
    border: 0;
    border-radius: var(--radius-md);
    transition: background-color var(--duration-fast) var(--ease-out);

    &:hover {
      background: var(--color-primary-hover);
    }

    &:focus-visible {
      @include focus-ring;
    }
  }

  // Alinhado à esquerda como o formulário: o botão fica longe do selo de leads.
  &__thanks {
    align-content: center;
    justify-items: start;
  }

  &__check {
    inline-size: var(--space-12);
    block-size: var(--space-12);
  }

  &__check-ring {
    fill: var(--color-success-soft);
  }

  &__check-mark {
    fill: none;
    stroke: var(--color-success);
    stroke-width: var(--border-width-strong);
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  &__thanks-title {
    display: grid;
    gap: var(--space-1);
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
  }

  &__thanks-text {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-regular);
    line-height: var(--line-height-base);
    color: var(--color-text-muted);
  }

  // Selo de leads: superfície escura nos dois temas, sobreposto ao canto do cartão.
  &__leads {
    @include dark.values;
    @include color-roles;

    position: absolute;
    inset-block-end: var(--space-4);
    inset-inline-end: calc(var(--space-2) * -1);
    display: flex;
    gap: var(--space-2);
    align-items: center;
    padding: var(--space-3) var(--space-4);
    color: var(--color-text);
    background: var(--color-surface);
    border: var(--border-width) solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);

    @include up(lg) {
      inset-inline-end: calc(var(--space-16) * -1);
    }
  }

  &__leads-value {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    font-variant-numeric: tabular-nums;
  }

  // O número novo sobe no lugar do anterior.
  &__leads-number {
    display: inline-block;
  }

  &__leads-text {
    display: grid;
    font-size: var(--font-size-xs);
    line-height: var(--line-height-tight);
  }

  &__leads-period {
    color: var(--color-text-muted);
  }

  &__leads-icon {
    color: var(--color-success);
  }
}
</style>
