import { describe, expect, it } from 'vitest'

import { submissionBody } from '../publicForms'

describe('submissionBody', () => {
  it('sem anexos: JSON puro', () => {
    expect(submissionBody({ answers: { nome: 'Ana' }, website: '', files: { doc: [] } })).toEqual({
      answers: { nome: 'Ana' },
      website: '',
    })
  })

  it('leva o tempo de preenchimento quando medido, no JSON e no multipart', () => {
    expect(submissionBody({ answers: {}, website: '', durationSeconds: 42 })).toEqual({
      answers: {},
      website: '',
      duration_seconds: 42,
    })

    const file = new File(['a'], 'a.pdf', { type: 'application/pdf' })
    const body = submissionBody({
      answers: {},
      website: '',
      files: { doc: [file] },
      durationSeconds: 42,
    }) as FormData
    expect(body.get('duration_seconds')).toBe('42')
  })

  it('com anexos: multipart com answers em texto JSON e files[<id>][]', () => {
    const a = new File(['a'], 'a.pdf', { type: 'application/pdf' })
    const b = new File(['b'], 'b.png', { type: 'image/png' })
    const body = submissionBody({
      answers: { nome: 'Ana', doc: null },
      website: '',
      files: { doc: [a, b] },
    })

    expect(body).toBeInstanceOf(FormData)
    const form = body as FormData
    expect(form.get('answers')).toBe('{"nome":"Ana","doc":null}')
    expect(form.get('website')).toBe('')
    expect(form.has('duration_seconds')).toBe(false)
    expect(form.getAll('files[doc][]').map((item) => (item as File).name)).toEqual([
      'a.pdf',
      'b.png',
    ])
  })
})
