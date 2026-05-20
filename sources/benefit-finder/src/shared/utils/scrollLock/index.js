// left: 37, up: 38, right: 39, down: 40,
// spacebar: 32, pageup: 33, pagedown: 34, end: 35, home: 36
// https://stackoverflow.com/a/4770179

// enable/disable scrolling
// useful for things like open dialog(s)
const keys = { 37: 1, 38: 1, 39: 1, 40: 1 }

const preventDefault = e => {
  e.preventDefault()
}

const preventDefaultForScrollKeys = e => {
  if (keys[e.keyCode]) {
    preventDefault(e)
    return false
  }
}

const wheelOpt = { passive: false }
const wheelEvent =
  'onwheel' in document.createElement('div') ? 'wheel' : 'mousewheel'

// call this to Enable
export const enableScroll = () => {
  window.addEventListener('DOMMouseScroll', preventDefault, false) // older FF
  window.addEventListener(wheelEvent, preventDefault, wheelOpt) // modern desktop
  window.addEventListener('touchmove', preventDefault, wheelOpt) // mobile
  window.addEventListener('keydown', preventDefaultForScrollKeys, false)
}

// call this to Disable
export const disableScroll = () => {
  window.removeEventListener('DOMMouseScroll', preventDefault, false)
  window.removeEventListener(wheelEvent, preventDefault, wheelOpt)
  window.removeEventListener('touchmove', preventDefault, wheelOpt)
  window.removeEventListener('keydown', preventDefaultForScrollKeys, false)
}

export default { disableScroll, enableScroll }
