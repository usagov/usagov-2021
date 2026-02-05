const dataLayerPush = (w, dataLayerObj, dedupe = true) => {
  const isObject = object => {
    return object != null && typeof object === 'object'
  }

  const isDeepEqual = (object1, object2) => {
    // get the keys of our two objects
    const objKeys1 = Object.keys(object1)
    const objKeys2 = Object.keys(object2)

    // if they have a different length we can return early
    if (objKeys1.length !== objKeys2.length) return false

    // compare each key
    for (const key of objKeys1) {
      const value1 = object1[key]
      const value2 = object2[key]

      const isObjects = isObject(value1) && isObject(value2)

      if (
        (isObjects && !isDeepEqual(value1, value2)) ||
        (!isObjects && value1 !== value2)
      ) {
        return false
      }
    }
    return true
  }

  if (w.dataLayer) {
    // get the last index of the dataLayer array
    const lastItem = { ...window.dataLayer[window.dataLayer.length - 1] }
    delete lastItem['gtm.uniqueEventId']
    delete lastItem.eventCallback

    if (dedupe === true) {
      // to prevent pushing duplicate objects unnecessarily, as long as our last item doesn't match our current data obj, we push
      isDeepEqual(lastItem, dataLayerObj) === false &&
        w.dataLayer.push(dataLayerObj)
    } else {
      w.dataLayer.push(dataLayerObj)
    }
  }
}

export default dataLayerPush
