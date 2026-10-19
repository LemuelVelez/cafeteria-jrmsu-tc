'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/vendor/cafeteria-barcode-decoder/decoder.js','utf8');
const window={};
vm.runInNewContext(source,{window});
const detector=window.CafeteriaBarcodeDecoder;
assert.equal(typeof detector.decode,'function');

// Draw a pixel-perfect, offline Code 128 B label with a valid weighted checksum.
const shapes=JSON.parse(source.match(/const shapes = (\[[^;]+\]);/)[1]);
const pattern=index=>Array.from(shapes[index],c=>c.toLowerCase().charCodeAt(0)-96);
const asCanvas=bars=>{
  const pixel=[];for(let i=0;i<24;i++)pixel.push(255);
  for(let i=0;i<bars.length;i++)for(let j=0;j<bars[i];j++)for(let k=0;k<3;k++)pixel.push(i%2===0?0:255);
  for(let i=0;i<24;i++)pixel.push(255);
  const width=pixel.length;
  const data=new Uint8ClampedArray(width*4);
  pixel.forEach((v,i)=>{data[4*i]=v;data[4*i+1]=v;data[4*i+2]=v;data[4*i+3]=255;});
  return {width,height:20,getContext:()=>({getImageData:()=>({data})})};
};
const value='JRMSU-001';
const symbols=Array.from(value,c=>c.charCodeAt(0)-32);
let checksum=104; symbols.forEach((c,i)=>{checksum+=c*(i+1);}); checksum%=103;
const codes=[104,...symbols,checksum,106];
const canvas=asCanvas(codes.flatMap(pattern));
assert.equal(detector.decode(canvas),value);
const damaged=asCanvas([104,...symbols,(checksum+1)%103,106].flatMap(pattern));
assert.equal(detector.decode(damaged),null,'An invalid Code 128 check digit must fail.');

const L=['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
const R=['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];
const GL=['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
const par=['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];
function bitsCanvas(bits){
  const pixels=['0'.repeat(22),bits,'0'.repeat(22)].join('');
  const pixel=Array.from(pixels).flatMap(c=>new Array(4).fill(c==='1'?0:255));
  const width=pixel.length;
  const data=new Uint8ClampedArray(width*4);
  pixel.forEach((v,i)=>{const n=4*i;data[n]=v;data[n+1]=v;data[n+2]=v;data[n+3]=255;});
  return {width,height:20,getContext:()=>({getImageData:()=>({data})})};
}
const ean='0036000291452';
const encoding='101'+Array.from(ean.slice(1,7),(c,i)=>par[+ean[0]][i]==='L'?L[+c]:GL[+c]).join('')+'01010'+Array.from(ean.slice(7),c=>R[+c]).join('')+'101';
assert.equal(encoding.length,95);
assert.equal(detector.decode(bitsCanvas(encoding)),'036000291452');
const demo='2000000000015';
const demoBits='101'+Array.from(demo.slice(1,7),(c,i)=>par[+demo[0]][i]==='L'?L[+c]:GL[+c]).join('')+'01010'+Array.from(demo.slice(7),c=>R[+c]).join('')+'101';
assert.equal(detector.decode(bitsCanvas(demoBits)),demo,'Demo EAN-13 must scan as the full EAN code.');
console.log('Barcode fallback: valid and tampered Code 128, UPC-A EAN encoding all pass.');
