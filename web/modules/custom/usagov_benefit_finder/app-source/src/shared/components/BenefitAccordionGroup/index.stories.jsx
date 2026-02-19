// import { useState } from 'react'
import BenefitAccordionGroup from './index.jsx'
import content from '@api/mock-data/current.js'
import * as en from '@locales/en/en.json'
import * as es from '@locales/es/es.json'

const { data } = JSON.parse(content)
const { benefits } = data
const b = benefits.slice(0, 7)
const entryKey = Object.keys(b[0])
const { resultsView } = en

const SourceIsEnglishBenefits = b.slice(5, 7)
SourceIsEnglishBenefits[0].benefit.SourceIsEnglish = true // ensure true
SourceIsEnglishBenefits[1].benefit.SourceIsEnglish = false // ensure false

let isExpandAll = false
const setExpandAll = () => (isExpandAll = !isExpandAll)

export default {
  component: BenefitAccordionGroup,
  tags: ['autodocs'],
  args: {
    data: b.slice(0, 2),
    entryKey: entryKey[0],
    ui: resultsView,
  },
}

export const Primary = {
  args: {
    data: b.slice(1, 3),
  },
}

export const ExpandAll = {
  args: {
    ...Primary.args,
    data: b.slice(3, 5),
    expandAll: true,
    isExpandAll: false,
    setExpandAll,
  },
}

// we only indicate sourceIsEnglish in es locale
export const SourceIsEnglish = {
  args: {
    ...Primary.args,
    data: SourceIsEnglishBenefits,
    ui: es.resultsView,
    expandAll: true,
  },
}
