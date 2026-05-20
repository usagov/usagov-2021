import ObfuscatedLink from './index.jsx'

export default {
  component: ObfuscatedLink,
  tags: ['autodocs'],
  args: {
    children: 'Obfuscated Link',
    href: 'https://www.google.com',
    noCarrot: false,
  },
}

export const Primary = {}

export const AccordionButton = {
  args: {
    children: 'Aplicar para el beneficio de suma global por muerte',
    noCarrot: true,
  },
}

export const NoCarrot = {
  args: {
    ...Primary,
    noCarrot: true,
  },
}
