import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Button from '@/components/ui/Button.vue'

describe('Button Component', () => {
  it('renders default button with provided slot text', () => {
    const wrapper = mount(Button, {
      slots: {
        default: 'Execute Briefing',
      },
    })
    expect(wrapper.text()).toContain('Execute Briefing')
    expect(wrapper.classes()).toContain('bg-nexo-accent-cyan')
  })

  it('renders gold variant and emits click event', async () => {
    const wrapper = mount(Button, {
      props: {
        variant: 'gold',
      },
      slots: {
        default: 'Executive Upgrade',
      },
    })
    expect(wrapper.classes()).toContain('bg-nexo-accent-gold')

    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('prevents click events when disabled or loading', async () => {
    const wrapper = mount(Button, {
      props: {
        disabled: true,
      },
      slots: {
        default: 'Disabled Action',
      },
    })
    expect(wrapper.attributes('disabled')).toBeDefined()
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })
})
