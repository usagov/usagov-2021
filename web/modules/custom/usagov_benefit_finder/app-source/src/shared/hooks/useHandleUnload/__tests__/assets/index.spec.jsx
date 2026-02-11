import { useState } from 'react'
import useHandleUnload from '../../index'

const IndexHTML = ({ hasData }) => {
  const [checkHasData, setCheckHasData] = useState(false)
  useHandleUnload(hasData || checkHasData)

  const handleOnChange = e => {
    e.target.value.length > 0 && setCheckHasData(true)
  }

  return (
    <div data-testid="unload-html">
      <input
        type="text"
        data-testid="unload-input-html"
        onChange={e => handleOnChange(e)}
      ></input>
    </div>
  )
}

export default IndexHTML
