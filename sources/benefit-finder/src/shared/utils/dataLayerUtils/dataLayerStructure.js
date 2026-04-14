const dataLayerStructure = {
  intro: {
    event: 'bf_page_change',
    bfData: { pageView: 'bf-intro', viewTitle: null },
  },
  lifeEventSection: {
    event: 'bf_page_change',
    bfData: {
      pageView: 'bf-form',
      viewTitle: null,
    },
  },
  errors: {
    event: 'bf_form_page_submit_attempt',
    bfData: {
      errors: null,
      errorCount: { number: null, string: null },
      formSuccess: false,
    },
  },
  modal: {
    event: 'bf_page_change',
    bfData: {
      pageView: 'bf-form-completion-modal',
      viewTitle: null,
    },
  },
  verifySelections: {
    event: 'bf_page_change',
    bfData: {
      pageView: 'bf-verify-selections',
      viewTitle: null,
    },
  },
  resultsView: {
    event: 'bf_page_change',
    bfData: {
      pageView: ['bf-result-eligible-view', 'bf-result-not-eligible-view'],
      viewTitle: null,
      eligibilityCount: {
        eligibleBenefitCount: null,
        moreInfoBenefitCount: null,
        notEligibleBenefitCount: null,
      },
    },
  },
  openAllBenefitAccordions: {
    event: 'bf_open_all_accordions',
    bfData: {
      accordionsOpen: true,
    },
  },
  benefitAccordion: {
    event: 'bf_accordion_open',
    bfData: {
      benefitTitle: null,
    },
  },
  benefitLink: {
    event: 'bf_benefit_link',
    bfData: {
      benefitTitle: null,
    },
  },
}

export default dataLayerStructure
