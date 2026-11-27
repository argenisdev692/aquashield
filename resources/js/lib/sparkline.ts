/**
 * Sparkline geometry — one pure function for the KPI tile's trend line.
 *
 * The series is server-bucketed (one value per day, zero included), so this
 * module only maps counts to a 2-D path. Keeping the maths out of the card
 * makes the shape testable without rendering, and keeps the component free of
 * a chart dependency for what is a 120×32 polyline.
 */

export interface SparklineGeometry {
    /** Open line path across the series. */
    line: string;
    /** Closed path that fills the area under the line. */
    area: string;
}

const round = (value: number): string => value.toFixed(2).replace(/\.?0+$/, '');

/**
 * `null` for fewer than two points: a single value is a dot, not a trend, and
 * drawing it would imply a direction the data does not contain.
 */
export function sparklineGeometry(
    series: readonly number[],
    width = 120,
    height = 32,
    padding = 2,
): SparklineGeometry | null {
    if (series.length < 2) {
        return null;
    }

    const min = Math.min(...series);
    const max = Math.max(...series);
    const span = max - min || 1;
    const stepX = (width - padding * 2) / (series.length - 1);
    const points = series.map((value, index) => {
        const x = padding + index * stepX;
        const y = padding + (1 - (value - min) / span) * (height - padding * 2);

        return `${round(x)},${round(y)}`;
    });

    const line = `M${points.join(' L')}`;

    return {
        line,
        area: `${line} L${round(width - padding)},${height} L${padding},${height} Z`,
    };
}
