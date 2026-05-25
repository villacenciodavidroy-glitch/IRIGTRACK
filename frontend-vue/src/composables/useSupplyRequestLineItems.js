/** Helpers for supply request line items with partial defective rejection */

export const getLineDefectiveQty = (item) => {
  if (!item) return 0
  return Math.max(0, parseInt(item.defective_quantity, 10) || 0)
}

export const getLineRequestedQty = (item) => {
  if (!item) return 0
  return Math.max(0, parseInt(item.quantity, 10) || 0)
}

export const getLineApprovedQty = (item) => {
  if (!item) return 0
  const requested = getLineRequestedQty(item)
  const defective = getLineDefectiveQty(item)
  if ((item.status || 'pending') === 'rejected') return 0
  return Math.max(0, requested - defective)
}

export const isItemFullyRejected = (item) => {
  if (!item) return false
  if ((item.status || 'pending') === 'rejected') return true
  const requested = getLineRequestedQty(item)
  const defective = getLineDefectiveQty(item)
  return requested > 0 && defective >= requested
}

/** @deprecated use isItemFullyRejected — kept for existing call sites */
export const isItemRejected = isItemFullyRejected

export const hasDefectiveUnits = (item) => {
  if (!item) return false
  return getLineDefectiveQty(item) > 0 && !isItemFullyRejected(item)
}

export const getTotalApprovedQuantity = (request) => {
  if (!request) return 0
  if (request.items && request.items.length > 0) {
    return request.items.reduce((sum, item) => sum + getLineApprovedQty(item), 0)
  }
  return parseInt(request.quantity, 10) || 0
}

export const getTotalDefectiveQuantity = (request) => {
  if (!request?.items?.length) return 0
  return request.items.reduce((sum, item) => sum + getLineDefectiveQty(item), 0)
}

/** Build lookup of defective units per catalog item (uuid/id keys). */
export const buildDefectMapFromRequests = (requests = []) => {
  const map = {}

  for (const request of requests) {
    if (!request?.items?.length) continue

    for (const line of request.items) {
      const defective = getLineDefectiveQty(line)
      if (defective <= 0) continue

      const keys = new Set(
        [line.item_id, line.item_id != null ? String(line.item_id) : null].filter(Boolean)
      )

      for (const key of keys) {
        if (!map[key]) {
          map[key] = {
            totalDefective: 0,
            itemName: line.item_name || 'Unknown item',
            reasons: []
          }
        }
        map[key].totalDefective += defective
        if (line.rejection_reason && !map[key].reasons.includes(line.rejection_reason)) {
          map[key].reasons.push(line.rejection_reason)
        }
      }
    }
  }

  return map
}

export const getSupplyDefectInfo = (supply, defectMap = {}) => {
  if (!supply) return null

  const keys = [supply.uuid, supply.id, supply.uuid != null ? String(supply.uuid) : null, supply.id != null ? String(supply.id) : null]
    .filter((k) => k != null && k !== '')

  for (const key of keys) {
    if (defectMap[key]) return defectMap[key]
  }

  const apiDefective = parseInt(supply.user_defective_quantity, 10) || 0
  if (apiDefective > 0) {
    return {
      totalDefective: apiDefective,
      itemName: supply.unit || supply.description || 'Unknown item',
      reasons: supply.latest_defect_reason ? [supply.latest_defect_reason] : []
    }
  }

  return null
}

export const getSupplyDefectiveQty = (supply, defectMap = {}) => {
  const info = getSupplyDefectInfo(supply, defectMap)
  return info?.totalDefective || 0
}
