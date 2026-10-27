import { gsap } from 'gsap'
import { CustomEase } from 'gsap/CustomEase'

import { ease } from './tokens'

gsap.registerPlugin(CustomEase)

/**
 * As curvas de `ease` (tokens) no formato do GSAP. Importado só por código que
 * já usa GSAP, para ele continuar fora do bundle principal.
 */
export const gsapEase = {
  out: CustomEase.create('formix-out', `M0,0 C${ease.out.join(',')} 1,1`),
  inOut: CustomEase.create('formix-in-out', `M0,0 C${ease.inOut.join(',')} 1,1`),
} as const
