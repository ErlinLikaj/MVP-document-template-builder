export function createBlock(component) {
  return {
    id: crypto.randomUUID(),
    type: component.type,
    grid: { ...component.defaultGrid },
    props: structuredClone(component.defaultProps),
  }
}

export function emptyStructure() {
  return {
    version: 1,
    sections: [],
  }
}

export function moveItem(list, fromIndex, toIndex) {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
    return list
  }

  const next = [...list]
  const [item] = next.splice(fromIndex, 1)
  next.splice(toIndex, 0, item)
  return next
}
