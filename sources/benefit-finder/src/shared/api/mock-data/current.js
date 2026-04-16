const content = `{
  "data": {
    "lifeEventForm": {
      "id": "death",
      "timeEstimate": "2-5 minutes",
      "titlePrefix": "",
      "title": "Benefit finder: death of a loved one",
      "summary": "<p><strong>We are sorry for your loss.&nbsp;</strong>Losing a loved one is hard. Finding help shouldn’t be.&nbsp;</p><p><strong>Answer a few questions and get a list of your potential benefits.</strong></p>",
      "relevantBenefits": [
        {
          "lifeEvent": {
            "title": "Benefit finder: retirement",
            "body": "<p><!--td {border: 1px solid #cccccc;}br {mso-data-placement:same-cell;}--><strong>Find retirement benefits</strong> including financial and health assistance.</p>",
            "link": "/benefit-finder/retirement",
            "cta": "Find retirement benefits including financial and health assistance.",
            "lifeEventId": "retirement"
          }
        },
        {
          "lifeEvent": {
            "title": "Benefit finder: disability",
            "body": "<p><strong>Find disability benefits</strong> to help with bills, education, jobs, and more.</p>",
            "link": "/benefit-finder/disability",
            "cta": "Find disability benefits to help with bills, education, jobs, and more.",
            "lifeEventId": "disability"
          }
        }
      ],
      "sectionsEligibilityCriteria": [
        {
          "section": {
            "heading": "About the applicant",
            "description": "<p><strong>About you:</strong> the applicant looking for benefits</p>",
            "fieldsets": [
              {
                "fieldset": {
                  "criteriaKey": "applicant_date_of_birth",
                  "legend": "Date of birth",
                  "required": true,
                  "hint": "For example: January 19 2000",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "applicant_date_of_birth",
                        "type": "Date",
                        "name": "applicant_date_of_birth",
                        "label": "Date of birth",
                        "hasChild": true,
                        "childDependencyOption": ">=18years",
                        "values": [
                          {
                            "default": "",
                            "value": {}
                          }
                        ]
                      }
                    }
                  ],
                                    "children": [
                    {
                      "fieldsets": [
                        {
                          "fieldset": {
                            "criteriaKey": "nested_child_criteria_test",
                            "legend": "nested_child_criteria_test",
                            "required": true,
                            "hint": "",
                            "inputs": [
                              {
                                "inputCriteria": {
                                  "id": "nested_child_criteria_test",
                                  "type": "Select",
                                  "name": "nested_child_criteria_test",
                                  "label": "nested_child_criteria_test status_2",
                                  "hasChild": false,
                                  "childDependencyOption": "",
                                  "values": [
                                    {
                                      "option": "Married",
                                      "value": "Married"
                                    },
                                    {
                                      "option": "Unmarried",
                                      "value": "Unmarried"
                                    },
                                    {
                                      "option": "Widowed",
                                      "value": "Widowed"
                                    },
                                    {
                                      "option": "Divorced",
                                      "value": "Divorced"
                                    }
                                  ]
                                }
                              }
                            ],
                            "children": []
                          }
                        }
                      ]
                    }
                  ]
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "applicant_relationship_to_the_deceased",
                  "legend": "Relationship to the deceased",
                  "required": true,
                  "hint": "",
                  "errorMessage": "Test override error label",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "applicant_relationship_to_the_deceased",
                        "type": "Select",
                        "name": "applicant_relationship_to_the_deceased",
                        "label": "Applicant's relationship to the deceased",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Spouse",
                            "value": "Spouse"
                          },
                          {
                            "option": "Child",
                            "value": "Child"
                          },
                          {
                            "option": "Parent",
                            "value": "Parent"
                          },
                          {
                            "option": "Other family member",
                            "value": "Other family member"
                          },
                          {
                            "option": "Personal or official representative",
                            "value": "Personal or official representative"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "applicant_marital_status",
                  "legend": "Marital status",
                  "required": true,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "applicant_marital_status",
                        "type": "Select",
                        "name": "applicant_marital_status",
                        "label": "Marital status",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Married",
                            "value": "Married"
                          },
                          {
                            "option": "Unmarried",
                            "value": "Unmarried"
                          },
                          {
                            "option": "Widowed",
                            "value": "Widowed"
                          },
                          {
                            "option": "Divorced",
                            "value": "Divorced"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "applicant_citizen_status",
                  "legend": "Are you a U.S. citizen or eligible non-citizen?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "applicant_citizen_status",
                        "type": "Radio",
                        "name": "applicant_citizen_status",
                        "label": "Are you a U.S. citizen or eligible non-citizen?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "applicant_care_for_child",
                  "legend": "Are you caring for the deceased's child who is under age 16 or has a disability?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "applicant_care_for_child",
                        "type": "Radio",
                        "name": "applicant_care_for_child",
                        "label": "Are you caring for a child of someone who is retired, has a disability, or has died, and the child is disabled or under the age of 16?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "applicant_paid_funeral_expenses",
                  "legend": "Did you pay for funeral or burial expenses?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "applicant_paid_funeral_expenses",
                        "type": "Radio",
                        "name": "applicant_paid_funeral_expenses",
                        "label": "Did you pay for funeral or burial expenses?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              }
            ]
          }
        },
        {
          "section": {
            "heading": "About the deceased",
            "description": "",
            "fieldsets": [
              {
                "fieldset": {
                  "criteriaKey": "deceased_date_of_death",
                  "legend": "Date of death",
                  "required": true,
                  "hint": "Date on the death certificate. For example: January 25 2024",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_date_of_death",
                        "type": "Date",
                        "name": "deceased_date_of_death",
                        "label": "Date of death",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "default": "",
                            "value": {}
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "deceased_death_location_is_US",
                  "legend": "Did the death happen in the U.S.?",
                  "required": false,
                  "hint": "Including the U.S. and its territories",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_death_location_is_US",
                        "type": "Radio",
                        "name": "deceased_death_location_is_US",
                        "label": "Did the person die in the U.S.?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "deceased_paid_into_SS",
                  "legend": "Did the deceased ever work and pay U.S. Social Security taxes?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_paid_into_SS",
                        "type": "Radio",
                        "name": "deceased_paid_into_SS",
                        "label": "Did the deceased ever work and pay Social Security taxes on their earnings?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "deceased_public_safety_officer",
                  "legend": "Was the deceased a public safety officer who died in the line of duty?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_public_safety_officer",
                        "type": "Radio",
                        "name": "deceased_public_safety_officer",
                        "label": "Was the deceased a public safety officer who died in the line of duty?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "deceased_miner",
                  "legend": "Did the deceased work in coal mines and die because of black lung disease?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_miner",
                        "type": "Radio",
                        "name": "deceased_miner",
                        "label": "Did the person work in the coal mines and their death was due to black lung disease (pneumoconiosis)?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "deceased_american_indian",
                  "legend": "Was the deceased an American Indian or Alaskan Native?",
                  "required": false,
                  "hint": "A member of a federally recognized American Indian Tribe or Alaska Native.",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_american_indian",
                        "type": "Radio",
                        "name": "deceased_american_indian",
                        "label": "Was the deceased a member of a federally recognized American Indian Tribe or Alaska Native?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "deceased_died_of_COVID",
                  "legend": "Was the death COVID-19 related?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_died_of_COVID",
                        "type": "Radio",
                        "name": "deceased_died_of_COVID",
                        "label": "Was the person’s death COVID-19 related?",
                        "hasChild": false,
                        "childDependencyOption": "",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": []
                }
              },
              {
                "fieldset": {
                  "criteriaKey": "deceased_served_in_active_military",
                  "legend": "Did the deceased actively serve in the U.S. military?",
                  "required": false,
                  "hint": "",
                  "inputs": [
                    {
                      "inputCriteria": {
                        "id": "deceased_served_in_active_military",
                        "type": "Radio",
                        "name": "deceased_served_in_active_military",
                        "label": "Did the deceased serve in active military service?",
                        "hasChild": true,
                        "childDependencyOption": "Yes",
                        "values": [
                          {
                            "option": "Yes",
                            "value": "Yes"
                          },
                          {
                            "option": "No",
                            "value": "No"
                          }
                        ]
                      }
                    }
                  ],
                  "children": [
                    {
                      "fieldsets": [
                        {
                          "fieldset": {
                            "criteriaKey": "deceased_service_status",
                            "legend": "What was the military service status of the deceased?",
                            "required": false,
                            "hint": "",
                            "inputs": [
                              {
                                "inputCriteria": {
                                  "id": "deceased_service_status",
                                  "type": "Select",
                                  "name": "deceased_service_status",
                                  "label": "What was the service status of the deceased?",
                                  "hasChild": false,
                                  "childDependencyOption": "",
                                  "values": [
                                    {
                                      "option": "Active-duty service member",
                                      "value": "Active-duty service member"
                                    },
                                    {
                                      "option": "Discharged under conditions other than dishonorable",
                                      "value": "Discharged under conditions other than dishonorable"
                                    },
                                    {
                                      "option": "Retired from the service",
                                      "value": "Retired from the service"
                                    },
                                    {
                                      "option": "Member of the National Guard/Reserves",
                                      "value": "Member of the National Guard/Reserves"
                                    }
                                  ]
                                }
                              }
                            ],
                            "children": []
                          }
                        }
                      ]
                    },
                    {
                      "fieldsets": [
                        {
                          "fieldset": {
                            "criteriaKey": "deceased_military_death_circumstance",
                            "legend": "Which option best applies to the death of the deceased?",
                            "required": false,
                            "hint": "",
                            "inputs": [
                              {
                                "inputCriteria": {
                                  "id": "deceased_military_death_circumstance",
                                  "type": "Select",
                                  "name": "deceased_military_death_circumstance",
                                  "label": "Which option applies to the deceased?",
                                  "hasChild": false,
                                  "childDependencyOption": "",
                                  "values": [
                                    {
                                      "option": "Died while on active duty",
                                      "value": "Died while on active duty"
                                    },
                                    {
                                      "option": "Died while on inactive-duty training",
                                      "value": "Died while on inactive-duty training"
                                    },
                                    {
                                      "option": "Died as a result of a service-related disability/illness",
                                      "value": "Died as a result of a service-related disability/illness"
                                    },
                                    {
                                      "option": "Died while receiving/traveling to VA care",
                                      "value": "Died while receiving/traveling to VA care"
                                    },
                                    {
                                      "option": "Died while receiving/eligible for VA compensation",
                                      "value": "Died while receiving/eligible for VA compensation"
                                    }
                                  ]
                                }
                              }
                            ],
                            "children": []
                          }
                        }
                      ]
                    },
                    {
                      "fieldsets": [
                        {
                          "fieldset": {
                            "criteriaKey": "deceased_grave_headstone",
                            "legend": "Is the deceased buried in an unmarked grave or with a privately purchased headstone?",
                            "required": false,
                            "hint": "",
                            "inputs": [
                              {
                                "inputCriteria": {
                                  "id": "deceased_grave_headstone",
                                  "type": "Radio",
                                  "name": "deceased_grave_headstone",
                                  "label": "Is the person buried in a grave with a privately purchased headstone or in an unmarked grave?",
                                  "hasChild": false,
                                  "childDependencyOption": "",
                                  "values": [
                                    {
                                      "option": "Yes",
                                      "value": "Yes"
                                    },
                                    {
                                      "option": "No",
                                      "value": "No"
                                    }
                                  ]
                                }
                              }
                            ],
                            "children": []
                          }
                        }
                      ]
                    }
                  ]
                }
              }
            ]
          }
        }
      ]
    },
    "benefits": [
      {
        "benefit": {
          "title": "COVID-19 funeral assistance",
          "summary": "<p>Financial assistance for burial and funeral costs for someone who died of COVID-19. To be eligible, you have not been reimbursed from an organization or agency.</p>",
          "SourceLink": "https://www.fema.gov/disasters/coronavirus/economic/funeral-assistance",
          "SourceIsEnglish": false,
          "agency": {
            "title": " Federal Emergency Management Agency (FEMA)",
            "summary": "<p>Federal Emergency Management Agency (FEMA) offers support to people during natural disasters and national emergencies, including housing and funeral assistance.</p>",
            "lede": "<p>Federal Emergency Management Agency (FEMA) offers support to people during natural disasters and national emergencies, including housing and funeral assistance.</p>"
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_died_of_COVID",
              "label": "The deceased's death was COVID-19 related",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_death_location_is_US",
              "label": "The deceased died in the U.S.",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_date_of_death",
              "label": "The deceased died after May 20th, 2020",
              "acceptableValues": [
                ">=05-20-2020"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a U.S. citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_paid_funeral_expenses",
              "label": "You paid for funeral or burial expenses and were not reimbursed",
              "acceptableValues": [
                "Yes"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Coal mine workers' compensation (black lung benefits) for surviving spouse",
          "summary": "<p>Compensation to surviving spouses of coal miners totally disabled by or whose deaths are attributable to pneumoconiosis.</p>",
          "SourceLink": "https://www.dol.gov/agencies/owcp/dcmwc/filing_guide_survivor",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Department of Labor (DOL)",
            "summary": "<p>Promotes and improves the welfare, working conditions, opportunities, benefits and rights of wage earners, job seekers, and retirees of the United States.</p>",
            "lede": "<p>Promotes and improves the welfare, working conditions, opportunities, benefits and rights of wage earners, job seekers, and retirees of the United States.</p>"
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_miner",
              "label": "The deceased worked in the coal mines and their death was due to black lung disease (pneumoconiosis)",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse",
              "acceptableValues": [
                "Spouse"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Presidential Memorial Certificate",
          "summary": "<p>An engraved Presidential Memorial Certificate (PMC) signed by the current president honoring the military service of a veteran or reservist.</p>",
          "SourceLink": "https://www.va.gov/burials-memorials/memorial-items/presidential-memorial-certificates/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member, discharged under conditions other than dishonorable, or member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty, died as a result of a service-related disability/illness, died while receiving/traveling to VA care, or died while receiving/eligible for VA compensation",
              "acceptableValues": [
                "Died while on active duty",
                "Died as a result of a service-related disability/illness",
                "Died while receiving/traveling to VA care",
                "Died while receiving/eligible for VA compensation"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Veterans burial allowance",
          "summary": "<p>Assistance with burial, funeral, and transportation costs of a deceased veteran.</p>",
          "SourceLink": "https://www.va.gov/burials-memorials/veterans-burial-allowance/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_date_of_death",
              "label": "The deceased died within the last two years",
              "acceptableValues": [
                "<2years"
              ]
            },
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died as a result of a service-related disability/illness, died while receiving/traveling to VA care, or died while receiving/eligible for VA compensation",
              "acceptableValues": [
                "Died as a result of a service-related disability/illness",
                "Died while receiving/traveling to VA care",
                "Died while receiving/eligible for VA compensation"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            },
            {
              "criteriaKey": "applicant_paid_funeral_expenses",
              "label": "You paid for funeral or burial expenses",
              "acceptableValues": [
                "Yes"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors pension for spouse",
          "summary": "<p>Monthly payments to surviving spouses of wartime veterans with certain income and net worth limits.</p>",
          "SourceLink": "https://www.va.gov/pension/survivors-pension/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is unmarried or widowed",
              "acceptableValues": [
                "Unmarried",
                "Widowed"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse",
              "acceptableValues": [
                "Spouse"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors pension for child with disabilities",
          "summary": "<p>Monthly payments to qualified unmarried dependent children with disabilities of wartime veterans with certain income and net worth limits.</p>",
          "SourceLink": "https://www.va.gov/pension/survivors-pension/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "applicant_date_of_birth",
              "label": "You are under 18 years",
              "acceptableValues": [
                ">18years"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is unmarried",
              "acceptableValues": [
                "Unmarried"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: child",
              "acceptableValues": [
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Veterans medallions",
          "summary": "<p>A headstone medallion, grave marker, and Presidential Memorial Certificate for eligible veterans buried in a private cemetery.</p>",
          "SourceLink": "https://www.va.gov/burials-memorials/memorial-items/headstones-markers-medallions/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member, discharged under conditions other than dishonorable, or a member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "deceased_grave_headstone",
              "label": "The person is buried in a grave with a privately purchased headstone or an unmarked grave",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Veterans headstone and grave marker",
          "summary": "<p>A headstone, grave or niche marker, or medallion to honor a veteran, service member, or eligible family member.</p>",
          "SourceLink": "https://www.va.gov/burials-memorials/memorial-items/headstones-markers-medallions/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member, discharged under conditions other than dishonorable, or member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "deceased_grave_headstone",
              "label": "The person is buried in a grave with a privately purchased headstone or an unmarked grave",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Life insurance for survivors of veterans",
          "summary": "<p>Payment from a veteran's or service member's life insurance policy.</p>",
          "SourceLink": "https://www.benefits.va.gov/INSURANCE/sglivgli.asp",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member, discharged under conditions other than dishonorable, retired from the service, or member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable",
                "Retired from the service",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Dependency and Indemnity Compensation (DIC)",
          "summary": "<p>Tax-free financial assistance to eligible dependents, spouses, or parents of service members and veterans.</p>",
          "SourceLink": "https://www.va.gov/disability/dependency-indemnity-compensation/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member or discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty, died as a result of a service-related disability/illness, or died while receiving/eligible for VA compensation",
              "acceptableValues": [
                "Died while on active duty",
                "Died as a result of a service-related disability/illness",
                "Died while receiving/eligible for VA compensation"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: child, parent, or spouse",
              "acceptableValues": [
                "Child",
                "Parent",
                "Spouse"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Civilian Health and Medical Program of the VA (CHAMPVA)",
          "summary": "<p>Health insurance for dependents and surviving spouses covering some health care services and supplies.</p>",
          "SourceLink": "https://www.va.gov/health-care/family-caregiver-benefits/champva/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member or discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty or died as a result of a service-related disability/illness",
              "acceptableValues": [
                "Died while on active duty",
                "Died as a result of a service-related disability/illness"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is: unmarried or widowed",
              "acceptableValues": [
                "Unmarried",
                "Widowed"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: child or spouse",
              "acceptableValues": [
                "Child",
                "Spouse"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Burial benefits",
          "summary": "<p>Burial and transport assistance for the deceased, and travel support for the spouse, children, and immediate family members of the service member.</p>",
          "SourceLink": "https://www.militaryonesource.mil/benefits/funeral-and-burial-benefits-for-service-members/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Department of Defense (DOD)",
            "summary": "<p>Provides support for qualified spouses, children, and other family members of deceased service members.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty member",
              "acceptableValues": [
                "Active-duty service member"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died on active duty",
              "acceptableValues": [
                "Died while on active duty"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Applicant's relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Lump-sum death benefit",
          "summary": "<p>Financial assistance of $255 to surviving spouses of a deceased person who qualified for Social Security benefits.</p>",
          "SourceLink": "https://www.ssa.gov/benefits/survivors/ifyou.html#h7",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Social Security Administration (SSA)",
            "summary": "<p>Administers Social Security, as well as disability insurance, and other benefits.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_paid_into_SS",
              "label": "The deceased worked and paid Social Security taxes",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_date_of_death",
              "label": "The deceased died within the last two years",
              "acceptableValues": [
                "<2years"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a U.S. citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse or child",
              "acceptableValues": [
                "Spouse",
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Financial Assistance and Social Services (FASS) for deceased",
          "summary": "<p>Assistance with burial expenses of deceased American Indians who do not have resources for funeral expenses or with certain income and net worth limits.</p>",
          "SourceLink": "https://www.bia.gov/bia/ois/dhs/financial-assistance",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Department of Interior (DOI) - Indian Affairs",
            "summary": "<p>The Bureau of Indian Affairs enhances the quality of life and protects and improves the trust assets of American Indians, Indian tribes, and Alaska Natives.</p>",
            "lede": "<p>The Bureau of Indian Affairs enhances the quality of life and protects and improves the trust assets of American Indians, Indian tribes, and Alaska Natives.</p>"
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_american_indian",
              "label": "The deceased was a member of a federally recognized American Indian Tribe or an Alaska Native.",
              "acceptableValues": [
                "Yes"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors benefits for mothers/fathers with a child",
          "summary": "<p>Social Security survivors benefits to the person providing care for the child of a deceased worker.</p>",
          "SourceLink": "https://www.ssa.gov/forms/ssa-5.html",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Social Security Administration (SSA)",
            "summary": "<p>Administers Social Security, as well as disability insurance, and other benefits.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_paid_into_SS",
              "label": "The deceased worked and paid Social Security taxes",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is unmarried or widowed",
              "acceptableValues": [
                "Unmarried",
                "Widowed"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a US citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_care_for_child",
              "label": "You are caring for a child of someone who is retired, has a disability, or has died, and the child is disabled or under the age of 16",
              "acceptableValues": [
                "Yes"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Annuity for certain military surviving spouses",
          "summary": "<p>Financial support for surviving spouses of members of the uniformed services.</p>",
          "SourceLink": "https://militarypay.defense.gov/Benefits/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Department of Defense (DOD)",
            "summary": "<p>Provides support for qualified spouses, children, and other family members of deceased service members.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_date_of_death",
              "label": "Deceased died before 1978",
              "acceptableValues": [
                "<01-01-1978"
              ]
            },
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "You served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: retired from the service",
              "acceptableValues": [
                "Retired from the service"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse",
              "acceptableValues": [
                "Spouse"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Education benefits (GI Bill) for survivors",
          "summary": "<p>VA education benefits or job training for dependents and survivors of a veteran. You must have a high school or GED diploma to be eligible.</p>",
          "SourceLink": "https://www.va.gov/education/survivor-dependent-benefits/",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: Active-duty service member or discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty or died as a result of a service-related disability/illness",
              "acceptableValues": [
                "Died while on active duty",
                "Died as a result of a service-related disability/illness"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse or child",
              "acceptableValues": [
                "Spouse",
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors benefits for child with disabilities",
          "summary": "<p>Social Security survivors benefits to a child, stepchild, grandchild, or adopted child with disabilities of eligible workers. You only need one application to apply for all SSA benefits.</p>",
          "SourceLink": "https://www.ssa.gov/benefits/survivors/ifyou.html#h4",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Social Security Administration (SSA)",
            "summary": "<p>Administers Social Security, as well as disability insurance, and other benefits.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_paid_into_SS",
              "label": "The deceased worked and paid Social Security taxes",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_date_of_birth",
              "label": "You are under 18 years",
              "acceptableValues": [
                "<18years"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is unmarried",
              "acceptableValues": [
                "Unmarried"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a U.S. citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: child",
              "acceptableValues": [
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors benefits for spouse with disabilities",
          "summary": "<p>Benefits for surviving spouses and certain divorced spouses with disabilities. Eligible survivors must have a disability that prevents them from working for more than a year. You only need one application to apply for all SSA benefits.</p>",
          "SourceLink": "https://www.ssa.gov/benefits/survivors/ifyou.html#h2",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Social Security Administration (SSA)",
            "summary": "<p>Administers Social Security, as well as disability insurance, and other benefits.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_paid_into_SS",
              "label": "The deceased worked and paid Social Security taxes",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_date_of_birth",
              "label": "You are over 50 years",
              "acceptableValues": [
                ">=50years"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is widowed or divorced",
              "acceptableValues": [
                "Widowed",
                "Divorced"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a US citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse",
              "acceptableValues": [
                "Spouse"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Public safety officers' Educational Assistance Program",
          "summary": "<p>Financial assistance for higher education to spouses and children of police, fire, and emergency public safety officers killed in the line of duty.</p>",
          "SourceLink": "https://psob.bja.ojp.gov/PSOB_Education2018.pdf",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Department of Justice (DOJ)",
            "summary": "<p>Offers financial and educational support to help families of fallen public safety officers.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_public_safety_officer",
              "label": "The deceased was a public safety officer who died in the line of duty",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse or child",
              "acceptableValues": [
                "Spouse",
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Public safety officers' death benefits",
          "summary": "<p>A one-time benefit for survivors of law enforcement officers, firefighters, and other first responders whose deaths were related to an injury sustained during the line of duty.</p>",
          "SourceLink": "https://bja.ojp.gov/program/psob/benefits",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Department of Justice (DOJ)",
            "summary": "<p>Offers financial and educational support to help families of fallen public safety officers.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_public_safety_officer",
              "label": "The deceased was a public safety officer who died in the line of duty",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Burial flag",
          "summary": "<p>A United States flag for the coffin or urn in honor of the military service member.</p>",
          "SourceLink": "https://www.va.gov/burials-memorials/memorial-items/burial-flags/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member, discharged under conditions other than dishonorable, or member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Home loan program for survivors",
          "summary": "<p>A VA-backed home loan to surviving spouses of veterans.</p>",
          "SourceLink": "https://www.va.gov/housing-assistance/home-loans/surviving-spouse/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member or discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty, died as a result of a service-related disability/illness, Died while receiving/traveling to VA care, or died while receiving/eligible for VA compensation",
              "acceptableValues": [
                "Died while on active duty",
                "Died as a result of a service-related disability/illness",
                "Died while receiving/traveling to VA care",
                "Died while receiving/eligible for VA compensation"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is widowed",
              "acceptableValues": [
                "Widowed"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse",
              "acceptableValues": [
                "Spouse"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors benefits for child",
          "summary": "<p>Offers Social Security survivors benefits to a child, stepchild, grandchild, or adopted child of eligible workers.</p>",
          "SourceLink": "https://www.ssa.gov/benefits/survivors/ifyou.html#h4",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Social Security Administration (SSA)",
            "summary": "<p>Administers Social Security, as well as disability insurance, and other benefits.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_paid_into_SS",
              "label": "The deceased worked and paid Social Security taxes",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_date_of_birth",
              "label": "You are under 18 years",
              "acceptableValues": [
                "<18years"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is unmarried",
              "acceptableValues": [
                "Unmarried"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a U.S. citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Applicant's relationship to the deceased is: child",
              "acceptableValues": [
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivor benefit plan",
          "summary": "<p>Offers up to 55% of a service member's retired pay for survivors of active duty service members and some retired and reserve members.</p>",
          "SourceLink": "https://bja.ojp.gov/program/psob/benefits",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Department of Defense (DOD)",
            "summary": "<p>Provides support for qualified spouses, children, and other family members of deceased service members.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member, retired from the service, or member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Retired from the service",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty or died while on inactive-duty service training",
              "acceptableValues": [
                "Died while on active duty",
                "Died while on inactive-duty service training"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse or child",
              "acceptableValues": [
                "Spouse",
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Burial in VA national cemetery",
          "summary": "<p>Burial in VA national cemeteries for eligible veterans, service members, and some family members.</p>",
          "SourceLink": "https://www.va.gov/burials-memorials/eligibility/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member, discharged under conditions other than dishonorable, or member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Discharged under conditions other than dishonorable",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty, died as a result of a service-related disability/illness, died while receiving/traveling to VA care, or died while receiving/eligible for VA compensation",
              "acceptableValues": [
                "Died while on active duty",
                "Died as a result of a service-related disability/illness",
                "Died while receiving/traveling to VA care",
                "Died while receiving/eligible for VA compensation"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Death gratuity",
          "summary": "<p>Tax free payment of $100,000 to eligible survivors of members of the Armed Forces who died while on active duty or while serving in certain reserve statuses.</p>",
          "SourceLink": "https://militarypay.defense.gov/Benefits/Death-Gratuity/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Department of Defense (DOD)",
            "summary": "<p>Provides support for qualified spouses, children, and other family members of deceased service members.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "The deceased served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "The service status of the deceased is: active-duty service member or member of the National Guard/Reserves",
              "acceptableValues": [
                "Active-duty service member",
                "Member of the National Guard/Reserves"
              ]
            },
            {
              "criteriaKey": "deceased_military_death_circumstance",
              "label": "One of the following applies to the deceased: died while on active duty or Died while on inactive-duty service training",
              "acceptableValues": [
                "Died while on active duty",
                "Died while on inactive-duty service training"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse, child, parent, or other family member",
              "acceptableValues": [
                "Spouse",
                "Child",
                "Parent",
                "Other family member"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors pension for child",
          "summary": "<p>Monthly payments to qualified unmarried dependent children of deceased wartime veterans.</p>",
          "SourceLink": "https://www.va.gov/pension/survivors-pension/",
          "SourceIsEnglish": true,
          "agency": {
            "title": "Veterans Affairs Department (VA)",
            "summary": "<p>Provides a wide range of benefits in support of veterans, service members, and their families.</p>",
            "lede": ""
          },
          "lifeEvents": [
            "Benefit finder: death of a loved one"
          ],
          "eligibility": [
            {
              "criteriaKey": "deceased_served_in_active_military",
              "label": "You served in the active military",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "deceased_service_status",
              "label": "Your service status is: discharged under conditions other than dishonorable",
              "acceptableValues": [
                "Discharged under conditions other than dishonorable"
              ]
            },
            {
              "criteriaKey": "applicant_date_of_birth",
              "label": "You are under 18 years",
              "acceptableValues": [
                "<18years"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is unmarried",
              "acceptableValues": [
                "Unmarried"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: child",
              "acceptableValues": [
                "Child"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors benefits for parents",
          "summary": "<p>Social Security survivors benefits to parents of eligible workers.</p>",
          "SourceLink": "https://www.ssa.gov/benefits/survivors/ifyou.html#h5",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Social Security Administration (SSA)",
            "summary": "<p>Administers Social Security, as well as disability insurance, and other benefits.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_paid_into_SS",
              "label": "The deceased worked and paid Social Security taxes",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_date_of_birth",
              "label": "You are over 62 years",
              "acceptableValues": [
                ">=62years"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a US citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: parent",
              "acceptableValues": [
                "Parent"
              ]
            }
          ]
        }
      },
      {
        "benefit": {
          "title": "Survivors benefits for spouse",
          "summary": "<p>Social Security survivors benefits to surviving spouses and certain divorced spouses of eligible workers.</p>",
          "SourceLink": "https://www.ssa.gov/benefits/survivors/ifyou.html#h2",
          "SourceIsEnglish": false,
          "agency": {
            "title": "Social Security Administration (SSA)",
            "summary": "<p>Administers Social Security, as well as disability insurance, and other benefits.</p>",
            "lede": ""
          },
          "eligibility": [
            {
              "criteriaKey": "deceased_paid_into_SS",
              "label": "The deceased worked and paid Social Security taxes",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_date_of_birth",
              "label": "You are over 60 years",
              "acceptableValues": [
                ">=60years"
              ]
            },
            {
              "criteriaKey": "applicant_marital_status",
              "label": "Your marital status is widowed or divorced",
              "acceptableValues": [
                "Widowed",
                "Divorced"
              ]
            },
            {
              "criteriaKey": "applicant_citizen_status",
              "label": "You are a US citizen or eligible non-citizen",
              "acceptableValues": [
                "Yes"
              ]
            },
            {
              "criteriaKey": "applicant_relationship_to_the_deceased",
              "label": "Your relationship to the deceased is: spouse",
              "acceptableValues": [
                "Spouse"
              ]
            }
          ]
        }
      }
    ]
  },
  "method": "GET",
  "status": 200
}
`

export default content
