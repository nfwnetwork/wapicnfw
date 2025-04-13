const express = require('express');
const sharp = require('sharp');
const axios = require('axios');
const app = express();

app.use(express.json());

app.post('/render', async (req, res) => {
  const { imageUrl, logoUrl, headlineText, farbcode, textOverlay } = req.body;
  try {
    const imageBuffer = (await axios.get(imageUrl, { responseType: 'arraybuffer' })).data;
    const logoBuffer = (await axios.get(logoUrl, { responseType: 'arraybuffer' })).data;

    const edited = await sharp(imageBuffer)
      .resize(1000, 1000)
      .composite([
        { input: logoBuffer, top: 875, left: 860 }
      ])
      .jpeg()
      .toBuffer();

    res.set('Content-Type', 'image/jpeg');
    res.send(edited);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.listen(3000, () => console.log('Server ready on port 3000'));
