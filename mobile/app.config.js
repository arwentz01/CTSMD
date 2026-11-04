// Keep app.json as the source of existing settings and resize around Android's keyboard.
module.exports = ({config}) => ({
  ...config,
  android: {...config.android, softwareKeyboardLayoutMode: 'resize'},
});
