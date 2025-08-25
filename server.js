const express = require('express');
const app = express();
const port = process.env.PORT || 3000;

app.get('/', (req, res) => {
  res.send('Hello, World! This is my Node.js app on Render.');
});

app.listen(port, () => {
  console.log(`Server is running on port ${port}`);
});
