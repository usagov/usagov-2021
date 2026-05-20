const prependID = (options = {}) => {
  return {
    postcssPlugin: 'wrap-css-with-id',
    Once(root) {
      if (root.source.input.file && root.source.input.file.endsWith('.scss')) {
        root.walkRules(rule => {
          const ignoreClasses = options.ignoreClasses || []
          if (
            (!ignoreClasses.includes(rule.selector),
            !rule.selector.includes(options.ignoreID))
          ) {
            rule.selector = `#${options.id} ${rule.selector}`
          }
        })
      }
    },
  }
}

prependID.postcss = true

export default prependID
