import useHandleClassName from '../../index'

const IndexHTML = ({ className, utility }) => {
  const defaultClasses = ['usa-test-class-one', 'usa-test-class-two']
  const handleUtility =
    utility !== undefined && utility ? ['usa-test-class-utility'] : ''
  const utilityClasses = handleUtility

  return (
    <div
      data-testid="class-name-html"
      className={useHandleClassName({
        className,
        defaultClasses,
        utilityClasses,
      })}
    ></div>
  )
}

export default IndexHTML
