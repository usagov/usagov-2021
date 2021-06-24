# elem-dataset [![Build Status](https://travis-ci.org/awcross/elem-dataset.svg?branch=master)](https://travis-ci.org/awcross/elem-dataset)

> HTML5 [`HTMLElement.dataset`](https://developer.mozilla.org/en-US/docs/Web/API/HTMLElement/dataset) [ponyfill](https://ponyfill.com)

*Note that "true" and "false" values are [not allowed on boolean attributes](https://w3c.github.io/html/infrastructure.html#sec-boolean-attributes).*

## Install

```
$ npm install --save elem-dataset
```

## Usage

```js
import elementDataset from 'elem-dataset';

const element = document.querySelector('.foo');
const attributes = elementDataset(element);
```

## License

MIT © [Alex Cross](https://alexcross.io)
