/**
 * a parse our date object
 * @function
 * @param {string} uri - window.location.href
 * @param {array} data - selected data
 * @return {string} returns a uri with the urlParameters
 */
const buildURIParameter = (uri, data) => {
  const uriArray =
    data &&
    data.map(item => {
      const key = encodeURI(item.criteriaKey)
      const value =
        typeof item.values?.value === 'object'
          ? encodeURIComponent(JSON.stringify(item.values?.value))
          : encodeURIComponent(item.values?.value)
      // remove the hash part before operating on the uri
      const i = uri.indexOf('#')
      const hash = i === -1 ? '' : uri.substr(i)
      uri = i === -1 ? uri : uri.substr(0, i)

      const re = new RegExp('([?&])' + key + '=.*?(&|$)', 'i')
      const separator = uri.indexOf('?') !== -1 ? '&' : '?'
      if (uri.match(re)) {
        uri = uri.replace(re, '$1' + key + '=' + value + '$2')
      } else {
        uri = uri + separator + key + '=' + value
      }
      return uri + hash // finally append the hash as well
    })
  return data ? `${uriArray[uriArray.length - 1]}&shared=true` : ''
}

export default buildURIParameter
