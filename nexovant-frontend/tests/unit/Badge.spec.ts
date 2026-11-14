import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import Badge from '@/components/ui/Badge.vue'

describe('Badge Component', () => {
  it('renders confirmed verification badge with green styling', () => {
    const wrapper = mount(Badge, {
      props: {
        variant: 'confirmed',
      },
    })
    expect(wrapper.text()).toContain('Confirmed')
    expect(wrapper.classes()).toContain('text-emerald-400')
  })

  it('renders disputed verification badge with red styling', () => {
    const wrapper = mount(Badge, {
      props: {
        variant: 'disputed',
      },
    })
    expect(wrapper.text()).toContain('Disputed Claim')
    expect(wrapper.classes()).toContain('text-red-400')
  })

  it('renders custom slot label when provided', () => {
    const wrapper = mount(Badge, {
      props: {
        variant: 'gold',
      },
      slots: {
        default: 'Priority 1 Alert',
      },
    })
    expect(wrapper.text()).toContain('Priority 1 Alert')
    expect(wrapper.classes()).toContain('text-nexo-accent-gold')
  })
})
