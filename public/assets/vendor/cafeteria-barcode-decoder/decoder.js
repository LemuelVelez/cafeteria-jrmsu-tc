/* Locally authored scan-line decoder for Code 128B and EAN-13/UPC-A.
   Code 128 patterns reuse the permissively licensed ReportLab-derived patterns
   already shipped with the cafeteria's barcode-label renderer.
   Requires no network access or external API key. */
(() => {
  'use strict';
  const shapes = ["BaBbBb","BbBaBb","BbBbBa","AbAbBc","AbAcBb","AcAbBb","AbBbAc","AbBcAb","AcBbAb","BbAbAc","BbAcAb","BcAbAb","AaBbCb","AbBaCb","AbBbCa","AaCbBb","AbCaBb","AbCbBa","BbCbAa","BbAaCb","BbAbCa","BaCbAb","BbCaAb","CaBaCa","CaAbBb","CbAaBb","CbAbBa","CaBbAb","CbBaAb","CbBbAa","BaBaBc","BaBcBa","BcBaBa","AaAcBc","AcAaBc","AcAcBa","AaBcAc","AcBaAc","AcBcAa","BaAcAc","BcAaAc","BcAcAa","AaBaCc","AaBcCa","AcBaCa","AaCaBc","AaCcBa","AcCaBa","CaCaBa","BaAcCa","BcAaCa","BaCaAc","BaCcAa","BaCaCa","CaAaBc","CaAcBa","CcAaBa","CaBaAc","CaBcAa","CcBaAa","CaDaAa","BbAdAa","DcAaAa","AaAbBd","AaAdBb","AbAaBd","AbAdBa","AdAaBb","AdAbBa","AaBbAd","AaBdAb","AbBaAd","AbBdAa","AdBaAb","AdBbAa","BdAbAa","BbAaAd","DaCaAa","BdAaAb","AcDaAa","AaAbDb","AbAaDb","AbAbDa","AaDbAb","AbDaAb","AbDbAa","DaAbAb","DbAaAb","DbAbAa","BaBaDa","BaDaBa","DaBaBa","AaAaDc","AaAcDa","AcAaDa","AaDaAc","AaDcAa","DaAaAc","DaAcAa","AaCaDa","AaDaCa","CaAaDa","DaAaCa","BaAdAb","BaAbAd","BaAbCb","BcCaAaB"];
  const patterns = shapes.map(pattern => Array.from(pattern, c => c.toLowerCase().charCodeAt(0) - 96));
  const leftOdd = ['0001101','0011001','0010011','0111101','0100011','0110001','0101111','0111011','0110111','0001011'];
  const leftEven = ['0100111','0110011','0011011','0100001','0011101','0111001','0000101','0010001','0001001','0010111'];
  const right = ['1110010','1100110','1101100','1000010','1011100','1001110','1010000','1000100','1001000','1110100'];
  const parities = ['LLLLLL','LLGLGG','LLGGLG','LLGGGL','LGLLGG','LGGLLG','LGGGLL','LGLGLG','LGLGGL','LGGLGL'];
  const threshold = (pixels, width) => {
    const hist = new Array(256).fill(0);
    for (let x=0; x<width; x++) {
      const n=x*4; const gray=Math.round(pixels[n]*0.299 + pixels[n+1]*0.587 + pixels[n+2]*0.114);
      hist[gray]++;
    }
    // Otsu threshold helps with uneven campus/phone lighting.
    let sum=0;for (let i=0;i<256;i++)sum+=i*hist[i];
    let background=0,partial=0,best=-1,cut=127;
    for(let i=0;i<256;i++){
      background+=hist[i];if (!background)continue;
      const foreground=width-background;if (!foreground)break;
      partial+=i*hist[i];const separation=(sum*background-partial*width)**2/(background*foreground);
      if(separation>best){best=separation;cut=i;}
    }
    return cut;
  };
  const runsFor = (pixels,width) => {
    const cut=threshold(pixels,width);const runs=[];
    let last=-1,at=0;
    for(let x=0;x<width;x++){
      const j=x*4;const dark=(pixels[j]*0.299+pixels[j+1]*0.587+pixels[j+2]*0.114)<=cut?1:0;
      if(dark!==last){if(x>at)runs.push({dark:last,start:at,width:x-at});last=dark;at=x;}
    }
    if(width>at)runs.push({dark:last,start:at,width:width-at});
    return runs;
  };
  const similar=(measured,ideal) => {
    const actual=measured.reduce((a,b)=>a+b.width,0);
    const expected=ideal.reduce((a,b)=>a+b,0);
    const module=actual/expected;
    if(module<.6)return false;
    let deviation=0;
    for(let i=0;i<ideal.length;i++){
      const diff=Math.abs(measured[i].width/module-ideal[i]);
      if(diff>.77)return false;
      deviation+=diff;
    }
    return deviation<2.35;
  };
  const code128=(runs) => {
    for(let offset=0;offset<runs.length-19;offset++){
      if(!runs[offset].dark || !similar(runs.slice(offset,offset+6),patterns[104]))continue;
      const codes=[104]; let position=offset+6;
      for(let n=0;n<80 && position<runs.length;n++){
        if(position+7<=runs.length && similar(runs.slice(position,position+7),patterns[106])){
          if(codes.length<3)break;
          const check=codes.pop();let sum=104;
          for(let k=1;k<codes.length;k++)sum+=codes[k]*k;
          if(sum%103!==check)break;
          const symbols=codes.slice(1);
          if(symbols.some(code=>code>94))break; // this fallback accepts standard Code 128B print labels
          return symbols.map(code=>String.fromCharCode(code+32)).join('');
        }
        let found=-1;
        for(let code=0;code<106;code++){
          if(similar(runs.slice(position,position+6),patterns[code])){found=code;break;}
        }
        if(found<0)break;
        codes.push(found);position+=6;
      }
    }
    return null;
  };
  const upcCheck = value => {
    const digits=Array.from(value,Number);if(!/^\d{12,13}$/.test(value))return false;
    const payload=digits.slice(0,-1).reverse();
    const sum=payload.reduce((acc,digit,i)=>acc+digit*(i%2===0?3:1),0);
    return (10-sum%10)%10===digits[digits.length-1];
  };
  const ean=(pixels,width,runs) => {
    const bitAt=(x,cut) => {
      if(x<0||x>=width)return '0';
      const j=Math.floor(x)*4;
      return (pixels[j]*.299+pixels[j+1]*.587+pixels[j+2]*.114<=cut)?'1':'0';
    };
    const cut=threshold(pixels,width);
    for(let i=0;i<runs.length-59;i++){
      const candidate=runs.slice(i,i+3);
      if(candidate.length!==3||!candidate[0].dark||candidate[1].dark||!candidate[2].dark)continue;
      const base=candidate.reduce((sum,r)=>sum+r.width,0)/3;
      if(!similar(candidate,[1,1,1]))continue;
      const start=candidate[0].start;
      for(const factor of [1,.985,1.015,.965,1.035]){
        const unit=base*factor;
        let bits='';for(let n=0;n<95;n++)bits+=bitAt(start+(n+.5)*unit,cut);
        if(bits.slice(0,3)!=='101'||bits.slice(45,50)!=='01010'||bits.slice(92)!=='101')continue;
        let encoded='',parity='',valid=true;
        for(let n=0;n<6;n++){
          const sequence=bits.slice(3+n*7,10+n*7);
          const odd=leftOdd.indexOf(sequence),even=leftEven.indexOf(sequence);
          if(odd>=0){encoded+=odd;parity+='L';}else if(even>=0){encoded+=even;parity+='G';}else{valid=false;break;}
        }
        if(!valid)continue;
        const leading=parities.indexOf(parity);if(leading<0)continue;
        let result=String(leading)+encoded;
        for(let n=0;n<6;n++){
          const digit=right.indexOf(bits.slice(50+n*7,57+n*7));
          if(digit<0){valid=false;break;}result+=digit;
        }
        if(valid&&upcCheck(result))return result[0]==='0'?result.slice(1):result;
      }
    }
    return null;
  };
  function decode(canvas){
    const ctx=canvas.getContext('2d',{willReadFrequently:true});
    if(!ctx)return null;
    for(const fraction of [.25,.40,.50,.60,.75]){
      const line=ctx.getImageData(0,Math.floor(canvas.height*fraction),canvas.width,1).data;
      const runs=runsFor(line,canvas.width);
      const result=code128(runs)||ean(line,canvas.width,runs);
      if(result)return result;
    }
    return null;
  }
  window.CafeteriaBarcodeDecoder={decode,decodeCode128Runs:code128};
})();
